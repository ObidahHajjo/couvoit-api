<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Message persisted within a conversation thread.
 *
 * @property int                             $id
 * @property int                             $conversation_id
 * @property int                             $sender_person_id
 * @property string                          $body
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read Conversation               $conversation
 * @property-read Person                     $sender
 */
class ConversationMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'sender_person_id',
        'body',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the parent conversation.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the person who sent the message.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'sender_person_id');
    }
}
