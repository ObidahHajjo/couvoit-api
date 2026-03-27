<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportChatPresence extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status',
        'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const STATUS_ONLINE = 'online';
    public const STATUS_AWAY = 'away';
    public const STATUS_OFFLINE = 'offline';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isOnline(): bool
    {
        return $this->status === self::STATUS_ONLINE;
    }

    public function setOnline(): void
    {
        $this->update([
            'status' => self::STATUS_ONLINE,
            'last_seen_at' => now(),
        ]);
    }

    public function setAway(): void
    {
        $this->update([
            'status' => self::STATUS_AWAY,
            'last_seen_at' => now(),
        ]);
    }

    public function setOffline(): void
    {
        $this->update([
            'status' => self::STATUS_OFFLINE,
            'last_seen_at' => now(),
        ]);
    }
}
