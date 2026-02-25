<?php

namespace App\Services\Interfaces;

use Throwable;

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
     * Refresh an access token.
     *
     * @param string $refreshToken
     *
     * @return array<string, mixed>
     *
     * @throws Throwable Propagates any exception thrown by the postgres and service logic.
     */
    public function refresh(string $refreshToken): array;
}
