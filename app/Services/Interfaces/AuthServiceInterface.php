<?php

namespace App\Services\Interfaces;

use Throwable;

/**
 * Contract for authentication and session workflows.
 */
interface AuthServiceInterface
{
    /**
     * Register a new user and ensure a corresponding
     * local Person entity exists.
     *
     * @param string $email
     * @param string $password
     *
     * @return array<string, mixed>
     *
     * @throws Throwable                Propagates any lower-level exception from client or repository.
     */
    public function register(string $email, string $password): array;

    /**
     * Authenticate a user with email/password.
     *
     * @param string $email
     * @param string $password
     *
     * @return array<string, mixed>
     *
     * @throws Throwable Propagates any exception thrown by the postgres and service logic.
     */
    public function login(string $email, string $password): array;

    /**
     * Logout (remove all cached related to this user and refresh tokens)
     *
     * @return void
     */
    public function logout() :void;

    /**
     * Refresh an access token.
     *
     * @param string $refreshToken
     *
     * @return array<string, mixed>
     *
     * @throws Throwable Propagates any exception thrown by the postgres and service logic.
     */
    public function refresh(string $refreshToken): array;

    /**
     * Generate reset password token then send an email
     *
     * @param string $email user email
     * @return string email send status
     */
    public function forgetPassword(string $email) : string;

    /**
     * Reset user password
     * @param array $data reset password request payload
     *
     * @return string status
     */
    public function resetPassword(array $data): string;
}
