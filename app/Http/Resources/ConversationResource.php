<?php

namespace App\Http\Resources;

use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON resource for conversation list and detail payloads.
 */
class ConversationResource extends JsonResource
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
            ? $this->messages->reduce(function ($latest, $message) {
                if ($latest === null) {
                    return $message;
                }

                $latestCreatedAt = optional($latest->created_at)?->getTimestamp() ?? 0;
                $messageCreatedAt = optional($message->created_at)?->getTimestamp() ?? 0;

                if ($messageCreatedAt > $latestCreatedAt) {
                    return $message;
                }

                if ($messageCreatedAt === $latestCreatedAt && (int) $message->id > (int) $latest->id) {
                    return $message;
                }

                return $latest;
            }, null)
            : null;

        $participantState = $this->relationLoaded('participantStates')
            ? $this->participantStates->first()
            : null;

        $visibleLastMessageAt = $latestMessage?->created_at
            ?? $participantState?->cleared_at
            ?? $this->last_message_at;

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
            'last_message_at' => optional($visibleLastMessageAt)?->toISOString(),
            'cleared_at' => optional($participantState?->cleared_at)?->toISOString(),
            'latest_message' => $latestMessage ? new ConversationMessageResource($latestMessage) : null,
            'messages' => $this->whenLoaded('messages', fn () => ConversationMessageResource::collection($this->messages)),
        ];
    }
}
