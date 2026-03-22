<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stores per-person conversation state such as clearing timestamps.
 */
class ConversationParticipantState extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'person_id',
        'cleared_at',
    ];

    protected $casts = [
        'cleared_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the conversation that owns the state row.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the participant that owns the state row.
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
