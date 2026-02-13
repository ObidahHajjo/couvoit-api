<?php

namespace App\Services\Interfaces;

use App\Exceptions\ExternalServiceException;
use App\Exceptions\UnauthorizedException;

interface AuthServiceInterface
{
    /**
     * Register a new user via Supabase.
     *
     * - Creates user in Supabase Auth
     * - Creates corresponding local Person record
     *
     * @param string $email
     * @param string $password
     * @return array Supabase response payload
     *
     * @throws ExternalServiceException If Supabase request fails.
     */
    public function register(string $email, string $password): array;

    /**
     * Authenticate user via Supabase.
     *
     * @param string $email
     * @param string $password
     * @return array Supabase token payload
     *
     * @throws UnauthorizedException If credentials are invalid.
     * @throws ExternalServiceException If Supabase is unreachable or fails.
     */
    public function login(string $email, string $password): array;

    /**
     * Refresh an access token via Supabase.
     *
     * @param string $refreshToken
     * @return array Refreshed token payload
     *
     * @throws UnauthorizedException If refresh token is invalid.
     * @throws ExternalServiceException If Supabase request fails.
     */
    public function refresh(string $refreshToken): array;
}
