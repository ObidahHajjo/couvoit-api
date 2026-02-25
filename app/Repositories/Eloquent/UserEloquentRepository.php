<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;

final class UserEloquentRepository implements UserRepositoryInterface
{
    public function existsByEmail(string $email): bool
    {
        return User::query()
            ->where('email', $email)
            ->exists();
    }

    public function findByEmail(string $email): ?User
    {
        /** @var User|null $user */
        $user = User::query()
            ->where('email', $email)
            ->first();

        return $user;
    }

    public function findById(int $id): ?User
    {
        /** @var User|null $user */
        $user = User::query()->find($id);

        return $user;
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function create(array $attributes): User
    {
        /** @var User $user */
        $user = User::query()->create($attributes);

        return $user;
    }
}
