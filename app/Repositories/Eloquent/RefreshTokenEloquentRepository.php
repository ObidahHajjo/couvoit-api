<?php

/**
 * @author    [Developer Name]
 *
 * @description Eloquent implementation of RefreshTokenRepositoryInterface for managing refresh tokens.
 */

namespace App\Repositories\Eloquent;

use App\Exceptions\UnauthorizedException;
use App\Models\RefreshToken;
use App\Repositories\Interfaces\RefreshTokenRepositoryInterface;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Eloquent-backed implementation of refresh token persistence.
 *
 * @implements RefreshTokenRepositoryInterface
 */
final class RefreshTokenEloquentRepository implements RefreshTokenRepositoryInterface
{
    /**
     * Store a new refresh token for a user.
     *
     * @param  int  $userId  The ID of the user to associate the token with.
     * @param  string  $refreshTokenPlain  The plain refresh token to store (will be hashed).
     * @param  CarbonImmutable  $expiresAt  The expiration timestamp of the token.
     */
    public function store(int $userId, string $refreshTokenPlain, CarbonImmutable $expiresAt): void
    {
        $hash = $this->hash($refreshTokenPlain);

        RefreshToken::query()->create([
            'user_id' => $userId,
            'token_hash' => $hash,
            'expires_at' => $expiresAt,
            'revoked_at' => null,
        ]);
    }

    /**
     * Consume and invalidate a refresh token.
     *
     * @param  string  $refreshTokenPlain  The plain refresh token to consume.
     * @return int The ID of the user associated with the token.
     *
     * @throws UnauthorizedException If the token is invalid, expired, or already revoked.
     */
    public function consume(string $refreshTokenPlain): int
    {
        $hash = $this->hash($refreshTokenPlain);
        /** @var RefreshToken|null $row */
        $row = RefreshToken::query()
            ->where('token_hash', $hash)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $row) {
            throw new UnauthorizedException('Invalid refresh token.');
        }

        $row->forceFill(['revoked_at' => now()])->save();

        return (int) $row->user_id;
    }

    /**
     * Consume an existing refresh token and rotate with a new one.
     *
     * @param  string  $refreshTokenPlain  The current refresh token to consume.
     * @param  string  $newRefreshTokenPlain  The new refresh token to store.
     * @param  CarbonImmutable  $newExpiresAt  The expiration timestamp for the new token.
     * @return int The ID of the user associated with the token.
     *
     * @throws UnauthorizedException If the current token is invalid.
     */
    public function consumeAndRotate(string $refreshTokenPlain, string $newRefreshTokenPlain, CarbonImmutable $newExpiresAt): int
    {
        $userId = $this->consume($refreshTokenPlain);
        $this->store($userId, $newRefreshTokenPlain, $newExpiresAt);

        return $userId;
    }

    /**
     * Delete all refresh tokens for a specific user.
     *
     * @param  int  $userId  The ID of the user whose tokens should be deleted.
     */
    public function deleteAllByUserId(int $userId): void
    {
        RefreshToken::query()->where('user_id', $userId)->delete();
    }

    /**
     * Hash a refresh token using HMAC-SHA256.
     *
     * @param  string  $plain  The raw refresh token to hash.
     * @return string The hashed refresh token.
     *
     * @throws RuntimeException If JWT_SECRET is not set or decoding fails.
     */
    private function hash(string $plain): string
    {
        $secret = (string) config('jwt.secret');

        if ($secret === '') {
            throw new RuntimeException('JWT_SECRET is not set');
        }

        // same base64: support
        if (str_starts_with($secret, 'base64:')) {
            $decoded = base64_decode(substr($secret, 7), true);
            if ($decoded === false) {
                throw new RuntimeException('JWT_SECRET base64 decoding failed');
            }
            $secret = $decoded;
        }

        return hash_hmac('sha256', $plain, $secret);
    }
}
