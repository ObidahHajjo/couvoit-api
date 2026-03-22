<?php

namespace App\Repositories\Interfaces;

use Carbon\CarbonImmutable;

/**
 * Contract for refresh token persistence and rotation.
 */
interface RefreshTokenRepositoryInterface
{
    /**
     * Store hashed refresh token for a user (write-only).
     *
     * @param int $userId user unique identifier
     * @param string $refreshTokenPlain unique refresh token
     * @param CarbonImmutable $expiresAt expire datetime
     * @return void
     */
    public function store(int $userId, string $refreshTokenPlain, CarbonImmutable $expiresAt): void;

    /**
     *  Validate provided refresh token and rotate it:
     *  - marks current token revoked
     *
     * @param string $refreshTokenPlain unique refresh token
     * @return int owning user id
     */
    public function consume(string $refreshTokenPlain): int;

    /**
     * Convenience: consume old token and store a new one.
     *
     * @param string $refreshTokenPlain unique refresh token
     * @param string $newRefreshTokenPlain the new unique refresh token
     * @param CarbonImmutable $newExpiresAt token expire datetime
     * @return int user id (so service can issue new access token).
     */
    public function consumeAndRotate(string $refreshTokenPlain, string $newRefreshTokenPlain, CarbonImmutable $newExpiresAt): int;

    /**
     * Revoke all refresh token related to a user
     *
     * @param int $userId user unique identifier
     * @return void
     */
    public function deleteAllByUserId(int $userId): void;
}
