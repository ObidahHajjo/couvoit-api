<?php

namespace App\Events;

use App\Models\SupportChatSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportMessagesRead implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public SupportChatSession $session,
        public int $readerId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('support.session.'.$this->session->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'support.messages.read';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => (int) $this->session->id,
            'reader_id' => $this->readerId,
        ];
    }
}
