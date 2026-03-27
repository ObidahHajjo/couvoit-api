<?php

namespace App\Services\Interfaces;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Contract for user management services.
 */
interface UserServiceInterface
{
    /**
     * List all users with pagination.
     *
     * @param  int  $perPage  Number of items per page.
     * @return LengthAwarePaginator<int, User>
     */
    public function listUsers(int $perPage = 15): LengthAwarePaginator;

    /**
     * Delete a user.
     *
     * @param  User  $user  User to delete.
     * @param  int  $authUserId  Authenticated user ID.
     *
     * @throws \Throwable If the operation fails.
     */
    public function deleteUser(User $user, int $authUserId): void;
}
