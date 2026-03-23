<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Conversation between two participants around a trip.
 *
 * @property int                             $id
 * @property int                             $participant_one_id
 * @property int                             $participant_two_id
 * @property int                             $trip_id
 * @property \Illuminate\Support\Carbon|null $last_message_at
 * @property-read Person                     $participantOne
 * @property-read Person                     $participantTwo
 * @property-read Trip                       $trip
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ConversationMessage> $messages
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ConversationParticipantState> $participantStates
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ConversationHiddenMessage> $hiddenMessages
 */
class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'participant_one_id',
        'participant_two_id',
        'trip_id',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the first conversation participant.
     */
    public function participantOne(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'participant_one_id');
    }

    /**
     * Get the second conversation participant.
     */
    public function participantTwo(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'participant_two_id');
    }

    /**
     * Get the trip associated with the conversation.
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * Get the messages that belong to the conversation.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }

    /**
     * Get the per-participant state rows for this conversation.
     */
    public function participantStates(): HasMany
    {
        return $this->hasMany(ConversationParticipantState::class);
    }

    /**
     * Get hidden message markers for this conversation.
     */
    public function hiddenMessages(): HasMany
    {
        return $this->hasMany(ConversationHiddenMessage::class);
    }

    /**
     * Determine whether the given person participates in the conversation.
     *
     * @param int $personId Person identifier.
     */
    public function involvesPerson(int $personId): bool
    {
        return (int) $this->participant_one_id === $personId || (int) $this->participant_two_id === $personId;
    }
}
