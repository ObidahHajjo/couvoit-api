<?php

namespace App\Clients\Interfaces;

use App\Exceptions\ConflictException;
use App\Exceptions\ExternalServiceException;
use App\Exceptions\UnauthorizedException;

interface SupabaseAuthClientInterface
{
    /**
     * Register a new user in Supabase.
     *
     * @param string $email
     * @param string $password
     *
     * @return array<string, mixed>
     *
     * @throws ConflictException
     * @throws ExternalServiceException
     */
    public function signUp(string $email, string $password): array;

    /**
     * Authenticate user with email/password.
     *
     * @param string $email
     * @param string $password
     *
     * @return array<string, mixed>
     *
     * @throws UnauthorizedException
     * @throws ExternalServiceException
     */
    public function signInWithPassword(string $email, string $password): array;

    /**
     * Refresh an access token using a refresh token.
     *
     * @param string $refreshToken
     *
     * @return array<string, mixed>
     *
     * @throws UnauthorizedException
     * @throws ExternalServiceException
     */
    public function refreshToken(string $refreshToken): array;
}
