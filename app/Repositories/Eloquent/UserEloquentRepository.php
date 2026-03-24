<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;

/**
 * Eloquent-backed implementation of user persistence operations.
 */
final class UserEloquentRepository implements UserRepositoryInterface
{
    /** @inheritDoc */
    public function existsByEmail(string $email): bool
    {
        return User::query()
            ->where('email', $email)
            ->exists();
    }

    /** @inheritDoc */
    public function findByEmail(string $email): ?User
    {
        /** @var User|null $user */
        $user = User::withTrashed()
            ->where('email', $email)
            ->first();

        return $user;
    }

    /** @inheritDoc */
    public function findById(int $id): ?User
    {
        /** @var User|null $user */
        $user = User::query()->find($id);

        return $user;
    }

    /** @inheritDoc */
    public function create(array $attributes): User
    {
        /** @var User $user */
        $user = User::query()->create($attributes);

        return $user;
    }

    /** @inheritDoc */
    public function softDelete(int $userId): void{
        /** @var User $user */
        $user = User::withoutTrashed()->findOrFail($userId);
        $user->is_active = false;
        $user->save();
        $user->delete();
    }

    /** @inheritDoc */
    public function restore(User $user): void
    {
        $user->restore();
        $user->forceFill([
            'is_active' => true,
        ])->save();
    }

    /** @inheritDoc */
    public function count(): int
    {
        return User::query()->count();
    }

    /** @inheritDoc */
    public function paginateWithRelations(int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return User::query()
            ->with(['person', 'role'])
            ->paginate($perPage);
    }

    /** @inheritDoc */
    public function findByPersonId(int $personId): User
    {
        /** @var User $user */
        $user = User::query()
            ->where('person_id', $personId)
            ->firstOrFail();

        return $user;
    }

    /** @inheritDoc */
    public function update(User $user, array $attributes): void
    {
        $user->update($attributes);
    }
}
