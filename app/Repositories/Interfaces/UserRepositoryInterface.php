<?php

namespace App\Repositories\Interfaces;

use App\Models\User;

/**
 * Interface UserRepositoryInterface
 *
 * Contract for user persistence operations.
 */
interface UserRepositoryInterface
{
    /**
     * Determine whether a user exists for the given email address.
     *
     * @param string $email User email address.
     *
     * @return bool True when a matching user exists, otherwise false.
     */
    public function existsByEmail(string $email): bool;

    /**
     * Find a user by email address.
     *
     * @param string $email User email address.
     *
     * @return User|null The matching user, or null when not found.
     */
    public function findByEmail(string $email): ?User;

    /**
     * Find a user by primary identifier.
     *
     * @param int $id User identifier.
     *
     * @return User|null The matching user, or null when not found.
     */
    public function findById(int $id): ?User;

    /**
     * Create and persist a new user.
     *
     * @param array<string, mixed> $attributes User attributes for creation.
     *
     * @return User The newly created user instance.
     */
    public function create(array $attributes): User;

    /**
     * Soft delete a user by identifier.
     *
     * @param int $userId User identifier.
     *
     * @return void
     */
    public function softDelete(int $userId): void;

    /**
     * Restore a previously soft-deleted user.
     *
     * @param User $user Soft-deleted user instance to restore.
     *
     * @return void
     */
    public function restore(User $user): void;
}
