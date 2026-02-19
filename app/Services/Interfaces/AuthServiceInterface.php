<?php

namespace App\Services\Interfaces;

use App\Exceptions\ExternalServiceException;
use Throwable;

interface AuthServiceInterface
{
    /**
     * Register a new user in Supabase and ensure a corresponding
     * local Person entity exists.
     *
     * @param string $email
     * @param string $password
     *
     * @return array<string, mixed>
     *
     * @throws ExternalServiceException If Supabase response does not contain a valid user ID.
     * @throws Throwable                Propagates any lower-level exception from client or repository.
     */
    public function register(string $email, string $password): array;

    /**
     * Authenticate a user with email/password via Supabase.
     *
     * @param string $email
     * @param string $password
     *
     * @return array<string, mixed>
     *
     * @throws Throwable Propagates any exception thrown by the Supabase client.
     */
    public function login(string $email, string $password): array;

    /**
     * Refresh an access token using a Supabase refresh token.
     *
     * @param string $refreshToken
     *
     * @return array<string, mixed>
     *
     * @throws Throwable Propagates any exception thrown by the Supabase client.
     */
    public function refresh(string $refreshToken): array;
}
