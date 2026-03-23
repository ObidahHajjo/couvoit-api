<?php

namespace App\Events;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast event dispatched when a chat message is sent.
 */
class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new chat message broadcast event.
     */
    public function __construct(
        public Conversation $conversation,
        public ConversationMessage $message,
    ) {}

    /**
     * Determine the channels the event should broadcast on.
     *
     * @return array<int, mixed>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.conversation.'.$this->conversation->id),
            new PrivateChannel('chat.user.'.$this->conversation->participant_one_id),
            new PrivateChannel('chat.user.'.$this->conversation->participant_two_id),
        ];
    }

    /**
     * Get the event broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'chat.message.sent';
    }

    /**
     * Get the payload broadcast with the event.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => (int) $this->conversation->id,
            'trip_id' => $this->conversation->trip_id !== null ? (int) $this->conversation->trip_id : null,
            'message' => [
                'id' => (int) $this->message->id,
                'body' => (string) $this->message->body,
                'sender_person_id' => (int) $this->message->sender_person_id,
                'created_at' => optional($this->message->created_at)?->toISOString(),
            ],
        ];
    }
}
