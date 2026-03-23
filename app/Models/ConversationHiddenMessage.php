<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stores per-person hidden chat messages.
 *
 * @property int               $id
 * @property int               $conversation_id
 * @property int               $person_id
 * @property int               $conversation_message_id
 * @property-read Conversation $conversation
 * @property-read Person       $person
 * @property-read ConversationMessage $message
 */
class ConversationHiddenMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'person_id',
        'conversation_message_id',
    ];

    /**
     * Get the conversation that owns the hidden-message state.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the person that hid the message.
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Get the hidden conversation message.
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class, 'conversation_message_id');
    }
}
