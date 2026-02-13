<?php

namespace App\Clients\Interfaces;

interface SupabaseAuthClientInterface
{
    /**
     * Signup a user in Supabase.
     *
     * @return array<string,mixed> Supabase JSON response
     */
    public function signUp(string $email, string $password): array;

    /**
     * Login using password grant.
     *
     * @return array<string,mixed> Supabase JSON response
     */
    public function signInWithPassword(string $email, string $password): array;

    /**
     * Refresh an access token.
     *
     * @return array<string,mixed> Supabase JSON response
     */
    public function refreshToken(string $refreshToken): array;
}
