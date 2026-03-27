<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persisted refresh token record for a user session.
 *
 * @property int                             $id
 * @property int                             $user_id
 * @property string                          $token_hash
 * @property \Illuminate\Support\Carbon    $expires_at
 * @property \Illuminate\Support\Carbon|null $revoked_at
 * @property-read User                       $user
 */
class RefreshToken extends Model
{
    protected $table = 'refresh_tokens';

    protected $fillable = [
        'user_id',
        'token_hash',
        'expires_at',
        'revoked_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * Get the user that owns the refresh token.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
