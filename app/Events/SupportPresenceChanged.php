<?php

namespace App\Events;

use App\Models\SupportChatPresence;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportPresenceChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public SupportChatPresence $presence,
        public string $userName,
    ) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('support.presence.'.$this->presence->user_id),
        ];

        $sessions = \App\Models\SupportChatSession::query()
            ->where('user_id', $this->presence->user_id)
            ->orWhere('admin_id', $this->presence->user_id)
            ->where('status', '!=', 'closed')
            ->pluck('id');

        foreach ($sessions as $sessionId) {
            $channels[] = new PrivateChannel('support.session.'.$sessionId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'support.presence.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => (int) $this->presence->user_id,
            'user_name' => $this->userName,
            'status' => (string) $this->presence->status,
            'last_seen_at' => optional($this->presence->last_seen_at)?->toISOString(),
        ];
    }
}
