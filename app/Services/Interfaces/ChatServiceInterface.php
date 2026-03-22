<?php

namespace App\Services\Interfaces;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Person;
use App\Models\Trip;
use Illuminate\Support\Collection;

/**
 * Contract for chat conversation workflows.
 */
interface ChatServiceInterface
{
    /**
     * List conversations visible to the authenticated person.
     *
     * @param Person $authPerson Authenticated person requesting the conversation list.
     *
     * @return Collection<int, Conversation>
     */
    public function listConversations(Person $authPerson): Collection;

    /**
     * Retrieve a conversation and ensure the authenticated person participates in it.
     *
     * @param int    $conversationId Conversation identifier.
     * @param Person $authPerson     Authenticated person requesting access.
     *
     * @return Conversation
     */
    public function getConversationForPerson(int $conversationId, Person $authPerson): Conversation;

    /**
     * Append a message to an existing conversation.
     *
     * @param int    $conversationId Conversation identifier.
     * @param Person $authPerson     Authenticated message author.
     * @param string $message        Message body to append.
     *
     * @return ConversationMessage
     */
    public function sendMessageInConversation(int $conversationId, Person $authPerson, string $message): ConversationMessage;

    /**
     * Clear the visible history of a conversation for the authenticated person only.
     *
     * @param int    $conversationId Conversation identifier.
     * @param Person $authPerson     Authenticated person clearing the conversation.
     *
     * @return Conversation
     */
    public function clearConversationForPerson(int $conversationId, Person $authPerson): Conversation;

    /**
     * Hide a single message inside a conversation for the authenticated person only.
     *
     * @param int    $conversationId Conversation identifier.
     * @param int    $messageId      Message identifier.
     * @param Person $authPerson     Authenticated person clearing the message.
     *
     * @return Conversation
     */
    public function clearMessageForPerson(int $conversationId, int $messageId, Person $authPerson): Conversation;

    /**
     * Hide multiple messages inside a conversation for the authenticated person only.
     *
     * @param int             $conversationId Conversation identifier.
     * @param array<int, int> $messageIds     Message identifiers to hide.
     * @param Person          $authPerson     Authenticated person clearing the messages.
     *
     * @return Conversation
     */
    public function clearMessagesForPerson(int $conversationId, array $messageIds, Person $authPerson): Conversation;

    /**
     * Open the conversation between the authenticated person and the trip driver.
     *
     * @param Trip   $trip       Trip whose driver should be contacted.
     * @param Person $authPerson Authenticated person opening the conversation.
     *
     * @return Conversation
     */
    public function openOrCreateDriverConversation(Trip $trip, Person $authPerson): Conversation;

    /**
     * Contact a trip driver and optionally send the first message.
     *
     * @param Trip        $trip       Trip whose driver should be contacted.
     * @param Person      $authPerson Authenticated person sending the message.
     * @param string|null $message    Optional initial message body.
     *
     * @return ConversationMessage|null
     */
    public function contactDriver(Trip $trip, Person $authPerson, ?string $message): ?ConversationMessage;

    /**
     * Open the conversation between a trip driver and one of the trip passengers.
     *
     * @param Trip   $trip       Trip shared by both participants.
     * @param Person $passenger  Passenger who should participate in the conversation.
     * @param Person $authPerson Authenticated driver opening the conversation.
     *
     * @return Conversation
     */
    public function openOrCreatePassengerConversation(Trip $trip, Person $passenger, Person $authPerson): Conversation;

    /**
     * Contact a passenger on a trip and optionally send the first message.
     *
     * @param Trip        $trip       Trip shared by both participants.
     * @param Person      $passenger  Passenger to contact.
     * @param Person      $authPerson Authenticated driver sending the message.
     * @param string|null $message    Optional initial message body.
     *
     * @return ConversationMessage|null
     */
    public function contactPassenger(Trip $trip, Person $passenger, Person $authPerson, ?string $message): ?ConversationMessage;
}
