<?php

namespace App\Services\Implementations;

use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Services\Interfaces\UserServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Resend\Laravel\Facades\Resend;

/**
 * Handles user-related business operations.
 *
 * @author Application Service
 *
 * @description Manages user listing, deletion, and notification workflows.
 */
readonly class UserService implements UserServiceInterface
{
    /**
     * Create a new user service instance.
     *
     * @param  UserRepositoryInterface  $users  The user repository
     */
    public function __construct(
        private UserRepositoryInterface $users,
    ) {}

    /**
     * List all users with pagination.
     *
     * @param  int  $perPage  Number of users per page
     * @return LengthAwarePaginator<User> Paginated list of users
     */
    public function listUsers(int $perPage = 15): LengthAwarePaginator
    {
        return $this->users->paginateWithRelations($perPage);
    }

    /**
     * Delete a user and send notification email.
     *
     * @param  User  $user  The user to delete
     * @param  int  $authUserId  The authenticated user ID performing the action
     */
    public function deleteUser(User $user, int $authUserId): void
    {
        $email = $user->email;
        $this->users->softDelete($user->id);

        try {
            Resend::emails()->send([
                'from' => config('mail.from.name').' <'.config('mail.from.address').'>',
                'to' => [$email],
                'subject' => 'Account Terminated - Violation of Terms',
                'html' => '<p>Hello,</p><p>We regret to inform you that your account has been terminated by an administrator due to a violation of our terms of service.</p><p>If you believe this is a mistake, please contact support.</p>',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send violation email: '.$e->getMessage());
        }
    }
}
