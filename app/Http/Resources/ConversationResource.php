<?php

namespace App\Http\Resources;

use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Person|null $authPerson */
        $authPerson = optional($request->user())->person;

        $otherParticipant = null;
        if ($authPerson !== null) {
            $otherParticipant = (int) $this->participant_one_id === (int) $authPerson->id
                ? $this->participantTwo
                : $this->participantOne;
        }

        $displayName = null;
        if ($otherParticipant !== null) {
            $displayName = trim((string) (($otherParticipant->first_name ?? '').' '.($otherParticipant->last_name ?? '')));
            if ($displayName === '') {
                $displayName = $otherParticipant->pseudo ?: 'User';
            }
        }

        $latestMessage = $this->relationLoaded('messages')
            ? $this->messages->sortByDesc('created_at')->first()
            : null;

        return [
            'id' => (int) $this->id,
            'participant' => $otherParticipant !== null ? [
                'id' => (int) $otherParticipant->id,
                'name' => $displayName,
                'pseudo' => $otherParticipant->pseudo,
            ] : null,
            'trip' => $this->trip !== null ? [
                'id' => (int) $this->trip->id,
                'from' => $this->trip->departureAddress?->city?->name,
                'to' => $this->trip->arrivalAddress?->city?->name,
            ] : null,
            'last_message_at' => optional($this->last_message_at)?->toISOString(),
            'latest_message' => $latestMessage ? new ConversationMessageResource($latestMessage) : null,
            'messages' => $this->whenLoaded('messages', fn () => ConversationMessageResource::collection($this->messages)),
        ];
    }
}
