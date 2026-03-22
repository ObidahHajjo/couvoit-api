<?php

namespace App\Security;

use App\Models\User;

/**
 * Contract for issuing and validating JWT access tokens.
 */
interface JwtIssuerInterface
{
    /**
     * Issue a signed access token for the given user.
     */
    public function issueAccessToken(User $user): string;

    /**
     * Decode and validate a signed access token.
     */
    public function verify(string $jwt): object;
}
