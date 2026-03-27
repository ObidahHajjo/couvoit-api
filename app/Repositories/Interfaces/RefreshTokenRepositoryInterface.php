<?php

namespace App\Repositories\Interfaces;

use Carbon\CarbonImmutable;

/**
 * Contract for refresh token persistence and rotation.
 */
interface RefreshTokenRepositoryInterface
{
    /**
     * Persist a hashed refresh token for a user session.
     *
     * @param int             $userId            User identifier.
     * @param string          $refreshTokenPlain Plain refresh token before hashing.
     * @param CarbonImmutable $expiresAt         Expiration timestamp.
     *
     * @return void
     */
    public function store(int $userId, string $refreshTokenPlain, CarbonImmutable $expiresAt): void;

    /**
     * Validate and consume a refresh token.
     *
     * Implementations typically revoke the consumed token so it cannot be reused.
     *
     * @param string $refreshTokenPlain Plain refresh token submitted by the client.
     *
     * @return int Owning user identifier.
     */
    public function consume(string $refreshTokenPlain): int;

    /**
     * Consume the current token and persist its replacement.
     *
     * @param string          $refreshTokenPlain    Current plain refresh token.
     * @param string          $newRefreshTokenPlain Replacement plain refresh token.
     * @param CarbonImmutable $newExpiresAt         Expiration timestamp for the replacement token.
     *
     * @return int Owning user identifier.
     */
    public function consumeAndRotate(string $refreshTokenPlain, string $newRefreshTokenPlain, CarbonImmutable $newExpiresAt): int;

    /**
     * Revoke every refresh token owned by a user.
     *
     * @param int $userId User identifier.
     *
     * @return void
     */
    public function deleteAllByUserId(int $userId): void;
}
