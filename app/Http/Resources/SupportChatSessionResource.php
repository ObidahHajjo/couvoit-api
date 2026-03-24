<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportChatSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'email' => $this->user->email,
                'name' => $this->user->person ? trim($this->user->person->first_name.' '.$this->user->person->last_name) : null,
            ]),
            'admin' => $this->whenLoaded('admin', fn () => $this->admin ? [
                'id' => $this->admin->id,
                'email' => $this->admin->email,
                'name' => $this->admin->person ? trim($this->admin->person->first_name.' '.$this->admin->person->last_name) : null,
            ] : null),
            'status' => $this->status,
            'subject' => $this->subject,
            'last_message_at' => $this->last_message_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'closed_at' => $this->closed_at?->toISOString(),
            'last_message' => $this->when(
                $this->relationLoaded('messages') && $this->messages->isNotEmpty(),
                fn () => [
                    'body' => $this->messages->first()?->body,
                    'created_at' => $this->messages->first()?->created_at?->toISOString(),
                ]
            ),
        ];
    }
}
