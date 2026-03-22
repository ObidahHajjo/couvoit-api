<?php

namespace App\Services\Implementations;

use App\Events\ChatMessageSent;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Person;
use App\Models\Trip;
use App\Services\Interfaces\ChatServiceInterface;
use Illuminate\Support\Collection;

class ChatService implements ChatServiceInterface
{
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
                'messages' => fn ($query) => $query->latest()->limit(1),
            ])
            ->orderByDesc('last_message_at')
            ->get();
    }

    public function getConversationForPerson(int $conversationId, Person $authPerson): Conversation
    {
        $conversation = Conversation::query()
            ->with([
                'participantOne',
                'participantTwo',
                'trip.departureAddress.city',
                'trip.arrivalAddress.city',
                'messages.sender',
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

    public function sendMessageInConversation(int $conversationId, Person $authPerson, string $message): ConversationMessage
    {
        $conversation = $this->getConversationForPerson($conversationId, $authPerson);

        return $this->appendMessage($conversation, $authPerson, $message);
    }

    public function openOrCreateDriverConversation(Trip $trip, Person $authPerson): Conversation
    {
        if ((int) $trip->person_id === (int) $authPerson->id) {
            throw new ForbiddenException('Driver cannot contact themselves.');
        }

        return $this->findOrCreateConversation($authPerson, $trip->driver, $trip);
    }

    public function contactDriver(Trip $trip, Person $authPerson, ?string $message): ?ConversationMessage
    {
        $conversation = $this->openOrCreateDriverConversation($trip, $authPerson);

        if ($message === null || trim($message) === '') {
            return null;
        }

        return $this->appendMessage($conversation, $authPerson, $message);
    }

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

    public function contactPassenger(Trip $trip, Person $passenger, Person $authPerson, ?string $message): ?ConversationMessage
    {
        $conversation = $this->openOrCreatePassengerConversation($trip, $passenger, $authPerson);

        if ($message === null || trim($message) === '') {
            return null;
        }

        return $this->appendMessage($conversation, $authPerson, $message);
    }

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

    private function appendMessage(Conversation $conversation, Person $sender, string $message): ConversationMessage
    {
        $body = trim($message);
        if ($body === '') {
            throw new ForbiddenException('Message cannot be empty.');
        }

        $created = $conversation->messages()->create([
            'sender_person_id' => $sender->id,
            'body' => $body,
        ]);

        $conversation->forceFill([
            'last_message_at' => $created->created_at,
            'updated_at' => now(),
        ])->save();

        $created->load('sender');

        event(new ChatMessageSent($conversation->fresh(), $created->fresh()));

        return $created;
    }
}
