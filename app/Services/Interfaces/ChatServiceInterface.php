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
     * @return Collection<int, Conversation>
     */
    public function listConversations(Person $authPerson): Collection;

    /**
     * Retrieve a conversation and ensure the authenticated person participates in it.
     */
    public function getConversationForPerson(int $conversationId, Person $authPerson): Conversation;

    /**
     * Append a message to an existing conversation.
     */
    public function sendMessageInConversation(int $conversationId, Person $authPerson, string $message): ConversationMessage;

    /**
     * Clear the visible history of a conversation for the authenticated person only.
     */
    public function clearConversationForPerson(int $conversationId, Person $authPerson): Conversation;

    /**
     * Open the conversation between the authenticated person and the trip driver.
     */
    public function openOrCreateDriverConversation(Trip $trip, Person $authPerson): Conversation;

    /**
     * Contact a trip driver and optionally send the first message.
     */
    public function contactDriver(Trip $trip, Person $authPerson, ?string $message): ?ConversationMessage;

    /**
     * Open the conversation between a trip driver and one of the trip passengers.
     */
    public function openOrCreatePassengerConversation(Trip $trip, Person $passenger, Person $authPerson): Conversation;

    /**
     * Contact a passenger on a trip and optionally send the first message.
     */
    public function contactPassenger(Trip $trip, Person $passenger, Person $authPerson, ?string $message): ?ConversationMessage;
}
