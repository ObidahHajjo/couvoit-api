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

        $otherParticipant = $this->resolveOtherParticipant($authPerson);
        $displayName = $this->resolveParticipantDisplayName($otherParticipant);
        $latestMessage = $this->resolveLatestMessage();
        $participantState = $this->resolveParticipantState();
        $visibleLastMessageAt = $this->resolveVisibleLastMessageAt($latestMessage, $participantState);

        return [
            'id' => (int) $this->id,
            'participant' => $this->buildParticipantData($otherParticipant, $displayName),
            'trip' => $this->buildTripData(),
            'last_message_at' => optional($visibleLastMessageAt)?->toISOString(),
            'cleared_at' => optional($participantState?->cleared_at)?->toISOString(),
            'latest_message' => $latestMessage !== null
                ? new ConversationMessageResource($latestMessage)
                : null,
            'messages' => $this->whenLoaded(
                'messages',
                fn () => ConversationMessageResource::collection($this->messages)
            ),
        ];
    }

    /**
     * Resolve the other participant of the conversation for the authenticated person.
     *
     * @param Person|null $authPerson
     * @return Person|null
     */
    private function resolveOtherParticipant(?Person $authPerson): ?Person
    {
        if ($authPerson === null) {
            return null;
        }

        return (int) $this->participant_one_id === (int) $authPerson->id
            ? $this->participantTwo
            : $this->participantOne;
    }

    /**
     * Resolve the participant display name.
     *
     * @param Person|null $participant
     * @return string|null
     */
    private function resolveParticipantDisplayName(?Person $participant): ?string
    {
        if ($participant === null) {
            return null;
        }

        $displayName = trim((string) (($participant->first_name ?? '') . ' ' . ($participant->last_name ?? '')));

        if ($displayName !== '') {
            return $displayName;
        }

        return $participant->pseudo ?: 'User';
    }

    /**
     * Resolve the latest loaded message.
     *
     * @return mixed
     */
    private function resolveLatestMessage()
    {
        if (! $this->relationLoaded('messages')) {
            return null;
        }

        return $this->messages->reduce(function ($latest, $message) {
            if ($latest === null) {
                return $message;
            }

            return $this->isMessageMoreRecent($message, $latest) ? $message : $latest;
        }, null);
    }

    /**
     * Determine whether the candidate message is more recent than the current one.
     *
     * @param mixed $candidate
     * @param mixed $current
     * @return bool
     */
    private function isMessageMoreRecent($candidate, $current): bool
    {
        $currentCreatedAt = optional($current->created_at)?->getTimestamp() ?? 0;
        $candidateCreatedAt = optional($candidate->created_at)?->getTimestamp() ?? 0;

        return $candidateCreatedAt > $currentCreatedAt
            || (
                $candidateCreatedAt === $currentCreatedAt
                && (int) $candidate->id > (int) $current->id
            );
    }

    /**
     * Resolve the loaded participant state.
     *
     * @return mixed
     */
    private function resolveParticipantState()
    {
        if (! $this->relationLoaded('participantStates')) {
            return null;
        }

        return $this->participantStates->first();
    }

    /**
     * Resolve the visible last message timestamp.
     *
     * @param mixed $latestMessage
     * @param mixed $participantState
     * @return mixed
     */
    private function resolveVisibleLastMessageAt($latestMessage, $participantState)
    {
        return $latestMessage?->created_at
            ?? $participantState?->cleared_at
            ?? $this->last_message_at;
    }

    /**
     * Build participant payload.
     *
     * @param Person|null $participant
     * @param string|null $displayName
     * @return array<string, mixed>|null
     */
    private function buildParticipantData(?Person $participant, ?string $displayName): ?array
    {
        if ($participant === null) {
            return null;
        }

        return [
            'id' => (int) $participant->id,
            'name' => $displayName,
            'pseudo' => $participant->pseudo,
        ];
    }

    /**
     * Build trip payload.
     *
     * @return array<string, mixed>|null
     */
    private function buildTripData(): ?array
    {
        if ($this->trip === null) {
            return null;
        }

        return [
            'id' => (int) $this->trip->id,
            'from' => $this->trip->departureAddress?->city?->name,
            'to' => $this->trip->arrivalAddress?->city?->name,
        ];
    }
}
