<?php

declare(strict_types=1);

/**
 * @author Covoiturage Team
 *
 * @description Default chat service implementation for conversation and messaging functionality.
 */

namespace App\Services\Implementations;

use App\Events\ChatMessageSent;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Models\Conversation;
use App\Models\ConversationHiddenMessage;
use App\Models\ConversationMessage;
use App\Models\ConversationMessageAttachment;
use App\Models\ConversationParticipantState;
use App\Models\Person;
use App\Models\Trip;
use App\Services\Interfaces\ChatServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @description Handles chat conversations, messaging, and attachment management between users.
 */
class ChatService implements ChatServiceInterface
{
    /**
     * @param  Person  $authPerson  The authenticated person
     * @return Collection<int, Conversation>
     */
    public function listConversations(Person $authPerson): Collection
    {
        return Conversation::query()
            ->where(function ($query) use ($authPerson) {
                $query->where('participant_one_id', $authPerson->id)
                    ->orWhere('participant_two_id', $authPerson->id);
            })
            ->with([
                'participantOne',
                'participantTwo',
                'trip.departureAddress.city',
                'trip.arrivalAddress.city',
                'participantStates' => fn ($query) => $query->where('person_id', $authPerson->id),
                'messages' => fn ($query) => $this->applyVisibleMessagesScope($query, $authPerson)
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->limit(1),
            ])
            ->orderByDesc('last_message_at')
            ->get();
    }

    /**
     * @param  int  $conversationId  The conversation ID
     * @param  Person  $authPerson  The authenticated person
     *
     * @throws NotFoundException If the conversation does not exist
     * @throws ForbiddenException If the person is not part of the conversation
     */
    public function getConversationForPerson(int $conversationId, Person $authPerson): Conversation
    {
        $conversation = Conversation::query()
            ->with([
                'participantOne',
                'participantTwo',
                'trip.departureAddress.city',
                'trip.arrivalAddress.city',
                'participantStates' => fn ($query) => $query->where('person_id', $authPerson->id),
                'messages' => fn ($query) => $this->applyVisibleMessagesScope($query, $authPerson)
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->with(['sender', 'attachments']),
            ])
            ->find($conversationId);

        if ($conversation === null) {
            throw new NotFoundException('Conversation not found.');
        }

        if (! $conversation->involvesPerson((int) $authPerson->id)) {
            throw new ForbiddenException('You are not part of this conversation.');
        }

        return $conversation;
    }

    /**
     * @param  int  $conversationId  The conversation ID
     * @param  Person  $authPerson  The authenticated person sending the message
     * @param  string  $message  The message body
     * @param  array<int, UploadedFile>  $attachments  Optional file attachments
     *
     * @throws ForbiddenException If the person is not part of the conversation
     */
    public function sendMessageInConversation(int $conversationId, Person $authPerson, string $message, array $attachments = []): ConversationMessage
    {
        $conversation = $this->getConversationForPerson($conversationId, $authPerson);

        return $this->appendMessage($conversation, $authPerson, $message, $attachments);
    }

    /**
     * @param  int  $conversationId  The conversation ID
     * @param  Person  $authPerson  The authenticated person
     *
     * @throws ForbiddenException If the person is not part of the conversation
     */
    public function clearConversationForPerson(int $conversationId, Person $authPerson): Conversation
    {
        $conversation = $this->getConversationForPerson($conversationId, $authPerson);

        ConversationParticipantState::query()->updateOrCreate(
            [
                'conversation_id' => $conversation->id,
                'person_id' => $authPerson->id,
            ],
            [
                'cleared_at' => now(),
            ]
        );

        return $this->getConversationForPerson($conversationId, $authPerson);
    }

    /**
     * @param  int  $conversationId  The conversation ID
     * @param  int  $messageId  The message ID to hide
     * @param  Person  $authPerson  The authenticated person
     *
     * @throws NotFoundException If the message does not exist
     */
    public function clearMessageForPerson(int $conversationId, int $messageId, Person $authPerson): Conversation
    {
        return $this->clearMessagesForPerson($conversationId, [$messageId], $authPerson);
    }

    /**
     * @param  int  $conversationId  The conversation ID
     * @param  array<int, int>  $messageIds  Array of message IDs to hide
     * @param  Person  $authPerson  The authenticated person
     *
     * @throws ForbiddenException If no messages are selected
     * @throws NotFoundException If any message does not exist
     */
    public function clearMessagesForPerson(int $conversationId, array $messageIds, Person $authPerson): Conversation
    {
        $conversation = $this->getConversationForPerson($conversationId, $authPerson);

        $normalizedMessageIds = array_values(array_unique(array_map('intval', $messageIds)));
        if ($normalizedMessageIds === []) {
            throw new ForbiddenException('At least one message must be selected.');
        }

        $messages = ConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->whereIn('id', $normalizedMessageIds)
            ->get();

        if ($messages->count() !== count($normalizedMessageIds)) {
            throw new NotFoundException('One or more messages were not found.');
        }

        foreach ($messages as $message) {
            ConversationHiddenMessage::query()->firstOrCreate([
                'conversation_id' => $conversation->id,
                'person_id' => $authPerson->id,
                'conversation_message_id' => $message->id,
            ]);
        }

        return $this->getConversationForPerson($conversationId, $authPerson);
    }

    /**
     * @param  Trip  $trip  The trip to create conversation for
     * @param  Person  $authPerson  The authenticated person
     *
     * @throws ForbiddenException If the person is the driver of the trip
     */
    public function openOrCreateDriverConversation(Trip $trip, Person $authPerson): Conversation
    {
        if ((int) $trip->person_id === (int) $authPerson->id) {
            throw new ForbiddenException('Driver cannot contact themselves.');
        }

        return $this->findOrCreateConversation($authPerson, $trip->driver, $trip);
    }

    /**
     * @param  Trip  $trip  The trip to contact driver about
     * @param  Person  $authPerson  The authenticated person
     * @param  string|null  $message  Optional message body
     * @param  array<int, UploadedFile>  $attachments  Optional file attachments
     * @return ConversationMessage|null The sent message or null if no content provided
     *
     * @throws ForbiddenException If the person is the driver of the trip
     */
    public function contactDriver(Trip $trip, Person $authPerson, ?string $message, array $attachments = []): ?ConversationMessage
    {
        $conversation = $this->openOrCreateDriverConversation($trip, $authPerson);

        if (($message === null || trim($message) === '') && $attachments === []) {
            return null;
        }

        return $this->appendMessage($conversation, $authPerson, (string) $message, $attachments);
    }

    /**
     * @param  Trip  $trip  The trip to create conversation for
     * @param  Person  $passenger  The passenger to create conversation with
     * @param  Person  $authPerson  The authenticated person (must be the driver)
     *
     * @throws ForbiddenException If the person is not the driver of the trip
     * @throws NotFoundException If the passenger is not on the trip
     */
    public function openOrCreatePassengerConversation(Trip $trip, Person $passenger, Person $authPerson): Conversation
    {
        if ((int) $trip->person_id !== (int) $authPerson->id) {
            throw new ForbiddenException('Only the driver can contact a passenger from this trip.');
        }

        $isPassenger = $trip->passengers()
            ->where('persons.id', $passenger->id)
            ->exists();

        if (! $isPassenger) {
            throw new NotFoundException('Passenger not found on this trip.');
        }

        return $this->findOrCreateConversation($authPerson, $passenger, $trip);
    }

    /**
     * @param  Trip  $trip  The trip to contact passenger about
     * @param  Person  $passenger  The passenger to contact
     * @param  Person  $authPerson  The authenticated person (must be the driver)
     * @param  string|null  $message  Optional message body
     * @param  array<int, UploadedFile>  $attachments  Optional file attachments
     * @return ConversationMessage|null The sent message or null if no content provided
     *
     * @throws ForbiddenException If the person is not the driver of the trip
     * @throws NotFoundException If the passenger is not on the trip
     */
    public function contactPassenger(Trip $trip, Person $passenger, Person $authPerson, ?string $message, array $attachments = []): ?ConversationMessage
    {
        $conversation = $this->openOrCreatePassengerConversation($trip, $passenger, $authPerson);

        if (($message === null || trim($message) === '') && $attachments === []) {
            return null;
        }

        return $this->appendMessage($conversation, $authPerson, (string) $message, $attachments);
    }

    /**
     * Find or create a conversation for two participants and a trip.
     */
    private function findOrCreateConversation(Person $first, Person $second, Trip $trip): Conversation
    {
        $participantIds = [(int) $first->id, (int) $second->id];
        sort($participantIds);

        /** @var Conversation $conversation */
        $conversation = Conversation::query()->firstOrCreate(
            [
                'participant_one_id' => $participantIds[0],
                'participant_two_id' => $participantIds[1],
            ],
            [
                'trip_id' => $trip->id,
                'last_message_at' => now(),
            ]
        );

        if ((int) ($conversation->trip_id ?? 0) !== (int) $trip->id) {
            $conversation->trip_id = $trip->id;
            $conversation->save();
        }

        return $conversation;
    }

    /**
     * Append a message to a conversation.
     */
    private function appendMessage(Conversation $conversation, Person $sender, string $message, array $attachments = []): ConversationMessage
    {
        $body = trim($message);
        if ($body === '' && $attachments === []) {
            throw new ForbiddenException('Message cannot be empty.');
        }

        /** @var ConversationMessage $created */
        $created = DB::transaction(function () use ($attachments, $body, $conversation, $sender): ConversationMessage {
            /** @var ConversationMessage $created */
            $created = $conversation->messages()->create([
                'sender_person_id' => $sender->id,
                'body' => $body,
            ]);

            foreach ($attachments as $attachment) {
                if (! $attachment instanceof UploadedFile) {
                    continue;
                }

                $storedPath = $attachment->storeAs(
                    sprintf('chat-attachments/%d', $conversation->id),
                    Str::uuid()->toString().'-'.$attachment->getClientOriginalName(),
                    'local'
                );

                $created->attachments()->create([
                    'disk' => 'local',
                    'path' => $storedPath,
                    'original_name' => $attachment->getClientOriginalName(),
                    'mime_type' => $attachment->getClientMimeType() ?: 'application/octet-stream',
                    'size_bytes' => $attachment->getSize() ?: 0,
                ]);
            }

            $conversation->forceFill([
                'last_message_at' => $created->created_at,
                'updated_at' => now(),
            ])->save();

            return $created;
        });

        $created->load(['sender', 'attachments']);

        event(new ChatMessageSent($conversation->fresh(), $created->fresh()));

        return $created;
    }

    /**
     * Apply per-person visibility rules to a conversation messages query.
     */
    private function applyVisibleMessagesScope($query, Person $authPerson)
    {
        return $query
            ->whereNotExists(function ($subQuery) use ($authPerson): void {
                $subQuery->selectRaw('1')
                    ->from('conversation_participant_states as cps')
                    ->whereColumn('cps.conversation_id', 'conversation_messages.conversation_id')
                    ->where('cps.person_id', $authPerson->id)
                    ->whereNotNull('cps.cleared_at')
                    ->whereColumn('conversation_messages.created_at', '<=', 'cps.cleared_at');
            })
            ->whereNotExists(function ($subQuery) use ($authPerson): void {
                $subQuery->selectRaw('1')
                    ->from('conversation_hidden_messages as chm')
                    ->whereColumn('chm.conversation_message_id', 'conversation_messages.id')
                    ->where('chm.person_id', $authPerson->id);
            })
            ->with('attachments');
    }

    /**
     * @param  int  $attachmentId  The attachment ID
     * @param  Person  $authPerson  The authenticated person
     * @return ConversationMessageAttachment|null The attachment if found and accessible
     *
     * @throws ForbiddenException If the person does not have access to the conversation
     */
    public function findAttachmentForPerson(int $attachmentId, Person $authPerson): ?ConversationMessageAttachment
    {
        $attachment = ConversationMessageAttachment::query()
            ->with('message')
            ->find($attachmentId);

        if ($attachment === null) {
            return null;
        }

        $this->getConversationForPerson((int) $attachment->message->conversation_id, $authPerson);

        return $attachment;
    }
}
