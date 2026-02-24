<?php

namespace App\Repositories\Eloquent;

use App\Exceptions\UnauthorizedException;
use App\Models\RefreshToken;
use App\Repositories\Interfaces\RefreshTokenRepositoryInterface;
use Carbon\CarbonImmutable;
use RuntimeException;

final class RefreshTokenEloquentRepository implements RefreshTokenRepositoryInterface
{
    /**
     * Store hashed refresh token for a user (write-only).
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
     * Validate provided refresh token and rotate it:
     * - marks current token revoked
     * - returns the owning user id
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

        if (!$row) {
            throw new UnauthorizedException('Invalid refresh token.');
        }

        $row->forceFill(['revoked_at' => now()])->save();

        return (int) $row->user_id;
    }

    /**
     * Convenience: consume old token and store a new one.
     * Returns user id (so service can issue new access token).
     */
    public function consumeAndRotate(string $refreshTokenPlain, string $newRefreshTokenPlain, CarbonImmutable $newExpiresAt): int
    {
        $userId = $this->consume($refreshTokenPlain);
        $this->store($userId, $newRefreshTokenPlain, $newExpiresAt);

        return $userId;
    }

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
