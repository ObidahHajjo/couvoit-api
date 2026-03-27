<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON resource for conversation message attachments.
 */
class ConversationMessageAttachmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) $this->original_name,
            'mime_type' => (string) $this->mime_type,
            'size_bytes' => (int) $this->size_bytes,
            'url' => route('chat.attachments.download', [
                'attachment' => (int) $this->id,
            ]),
        ];
    }
}
