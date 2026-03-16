<?php

namespace App\Repositories\Eloquent;

use App\Exceptions\UnauthorizedException;
use App\Models\RefreshToken;
use App\Repositories\Interfaces\RefreshTokenRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class RefreshTokenEloquentRepository implements RefreshTokenRepositoryInterface
{
    /** @inheritDoc */
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

    /** @inheritDoc */
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

    /** @inheritDoc */
    public function consumeAndRotate(string $refreshTokenPlain, string $newRefreshTokenPlain, CarbonImmutable $newExpiresAt): int
    {
        $userId = $this->consume($refreshTokenPlain);
        $this->store($userId, $newRefreshTokenPlain, $newExpiresAt);

        return $userId;
    }

    /** @inheritDoc */
    public function deleteAllByUserId(int $userId): void
    {
        RefreshToken::query()->where('user_id', $userId)->delete();
    }

    /**
     * hash a refresh token
     *
     * @param string $plain the raw refresh token
     * @return string ashed refresh token
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
