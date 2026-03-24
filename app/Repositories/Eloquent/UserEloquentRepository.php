<?php

/**
 * @author    [Developer Name]
 *
 * @description Eloquent implementation of UserRepositoryInterface for managing User entities.
 */

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Eloquent-backed implementation of user persistence operations.
 *
 * @implements UserRepositoryInterface
 */
final class UserEloquentRepository implements UserRepositoryInterface
{
    /**
     * Check if a user exists with the given email address.
     *
     * @param  string  $email  The email address to check.
     * @return bool True if a user with the email exists, false otherwise.
     */
    public function existsByEmail(string $email): bool
    {
        return User::query()
            ->where('email', $email)
            ->exists();
    }

    /**
     * Find a user by their email address (including soft-deleted).
     *
     * @param  string  $email  The email address to search for.
     * @return User|null The User entity if found, null otherwise.
     */
    public function findByEmail(string $email): ?User
    {
        /** @var User|null $user */
        $user = User::withTrashed()
            ->where('email', $email)
            ->first();

        return $user;
    }

    /**
     * Find a user by their ID.
     *
     * @param  int  $id  The ID of the user to retrieve.
     * @return User|null The User entity if found, null otherwise.
     */
    public function findById(int $id): ?User
    {
        /** @var User|null $user */
        $user = User::query()->find($id);

        return $user;
    }

    /**
     * Create a new user record.
     *
     * @param  array  $attributes  The data to create the user with.
     * @return User The newly created User entity.
     */
    public function create(array $attributes): User
    {
        /** @var User $user */
        $user = User::query()->create($attributes);

        return $user;
    }

    /**
     * Soft delete a user and mark them as inactive.
     *
     * @param  int  $userId  The ID of the user to soft delete.
     *
     * @throws ModelNotFoundException If user is not found.
     */
    public function softDelete(int $userId): void
    {
        /** @var User $user */
        $user = User::withoutTrashed()->findOrFail($userId);
        $user->is_active = false;
        $user->save();
        $user->delete();
    }

    /**
     * Restore a soft-deleted user and mark them as active.
     *
     * @param  User  $user  The user to restore.
     */
    public function restore(User $user): void
    {
        $user->restore();
        $user->forceFill([
            'is_active' => true,
        ])->save();
    }

    /**
     * Count total number of users.
     *
     * @return int The total number of users.
     */
    public function count(): int
    {
        return User::query()->count();
    }

    /**
     * Paginate all users with their relations.
     *
     * @param  int  $perPage  Number of results per page.
     * @return LengthAwarePaginator Paginated list of users with person and role relations.
     */
    public function paginateWithRelations(int $perPage = 15): LengthAwarePaginator
    {
        return User::query()
            ->with(['person', 'role'])
            ->paginate($perPage);
    }

    /**
     * Find a user by their associated person ID.
     *
     * @param  int  $personId  The ID of the associated person.
     * @return User The User entity.
     *
     * @throws ModelNotFoundException If user is not found.
     */
    public function findByPersonId(int $personId): User
    {
        /** @var User $user */
        $user = User::query()
            ->where('person_id', $personId)
            ->firstOrFail();

        return $user;
    }

    /**
     * Update an existing user record.
     *
     * @param  User  $user  The user to update.
     * @param  array  $attributes  The data to update the user with.
     */
    public function update(User $user, array $attributes): void
    {
        $user->update($attributes);
    }
}
