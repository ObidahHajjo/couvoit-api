<?php

namespace App\Events;

use App\Models\SupportChatSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportSessionJoined implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public SupportChatSession $session,
        public int $adminId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('support.session.'.$this->session->id),
            new PrivateChannel('support.admins'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'support.session.joined';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => (int) $this->session->id,
            'admin_id' => $this->adminId,
            'status' => (string) $this->session->status,
        ];
    }
}
