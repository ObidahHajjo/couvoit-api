<?php

namespace App\Events;

use App\Models\SupportChatMessage;
use App\Models\SupportChatSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public SupportChatSession $session,
        public SupportChatMessage $message,
    ) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('support.session.'.$this->session->id),
            new PrivateChannel('support.user.'.$this->session->user_id),
        ];

        if ($this->session->admin_id !== null) {
            $channels[] = new PrivateChannel('support.admin.'.$this->session->admin_id);
        }

        $channels[] = new PrivateChannel('support.admins');

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'support.message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => (int) $this->session->id,
            'message' => [
                'id' => (int) $this->message->id,
                'body' => (string) $this->message->body,
                'sender_id' => (int) $this->message->sender_id,
                'is_from_admin' => $this->message->is_from_admin,
                'is_read' => $this->message->is_read,
                'created_at' => optional($this->message->created_at)?->toISOString(),
            ],
        ];
    }
}
