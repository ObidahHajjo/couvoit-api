<?php

namespace App\Repositories\Interfaces;

use Carbon\CarbonImmutable;

interface RefreshTokenRepositoryInterface
{
    public function store(int $userId, string $refreshTokenPlain, CarbonImmutable $expiresAt): void;
    public function consume(string $refreshTokenPlain): int;
    public function consumeAndRotate(string $refreshTokenPlain, string $newRefreshTokenPlain, CarbonImmutable $newExpiresAt): int;

}
