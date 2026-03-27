<?php

namespace App\Security;

use App\Models\User;

/**
 * Contract for issuing and validating JWT access tokens.
 *
 * @author Covoiturage API Team
 *
 * @description Interface for JWT token services.
 */
interface JwtIssuerInterface
{
    /**
     * Issue a signed access token for the given user.
     *
     * @param  User  $user  The user to generate the token for
     * @return string The encoded JWT token
     */
    public function issueAccessToken(User $user): string;

    /**
     * Decode and validate a signed access token.
     *
     * @param  string  $jwt  The JWT token to verify
     * @return object The decoded token payload
     *
     * @throws RuntimeException If the token is invalid
     */
    public function verify(string $jwt): object;
}
