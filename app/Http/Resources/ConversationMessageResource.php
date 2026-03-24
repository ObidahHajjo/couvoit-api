<?php

namespace App\Http\Resources;

use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON resource for conversation messages.
 */
class ConversationMessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Person|null $authPerson */
        $authPerson = optional($request->user())->person;

        return [
            'id' => (int) $this->id,
            'body' => (string) $this->body,
            'sender' => $authPerson !== null && (int) $this->sender_person_id === (int) $authPerson->id ? 'me' : 'other',
            'sender_person_id' => (int) $this->sender_person_id,
            'created_at' => optional($this->created_at)?->toISOString(),
            'attachments' => ConversationMessageAttachmentResource::collection($this->whenLoaded('attachments')),
        ];
    }
}
