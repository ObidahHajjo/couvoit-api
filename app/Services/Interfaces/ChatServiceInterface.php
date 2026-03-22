<?php

namespace App\Services\Interfaces;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Person;
use App\Models\Trip;
use Illuminate\Support\Collection;

interface ChatServiceInterface
{
    public function listConversations(Person $authPerson): Collection;

    public function getConversationForPerson(int $conversationId, Person $authPerson): Conversation;

    public function sendMessageInConversation(int $conversationId, Person $authPerson, string $message): ConversationMessage;

    public function openOrCreateDriverConversation(Trip $trip, Person $authPerson): Conversation;

    public function contactDriver(Trip $trip, Person $authPerson, ?string $message): ?ConversationMessage;

    public function openOrCreatePassengerConversation(Trip $trip, Person $passenger, Person $authPerson): Conversation;

    public function contactPassenger(Trip $trip, Person $passenger, Person $authPerson, ?string $message): ?ConversationMessage;
}
