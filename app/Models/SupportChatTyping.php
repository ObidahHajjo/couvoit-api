<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportChatTyping extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'user_id',
        'typing_at',
    ];

    protected $casts = [
        'typing_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(SupportChatSession::class, 'session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isTyping(): bool
    {
        return $this->typing_at->gt(now()->subSeconds(5));
    }
}
