<?php

namespace App\Repositories\Interfaces;

use App\Models\User;

interface UserRepositoryInterface
{

    public function existsByEmail(string $email): bool;

    public function findByEmail(string $email): ?User;

    public function findById(int $id): ?User;

    /**
     * @param array<string,mixed> $attributes
     */
    public function create(array $attributes): User;
}
