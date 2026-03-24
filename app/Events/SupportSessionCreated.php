<?php

namespace App\Events;

use App\Models\SupportChatSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportSessionCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public SupportChatSession $session,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('support.admins'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'support.session.created';
    }

    public function broadcastWith(): array
    {
        return [
            'session' => [
                'id' => (int) $this->session->id,
                'user_id' => (int) $this->session->user_id,
                'status' => (string) $this->session->status,
                'subject' => $this->session->subject,
                'created_at' => optional($this->session->created_at)?->toISOString(),
            ],
        ];
    }
}
