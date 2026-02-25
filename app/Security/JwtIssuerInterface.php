<?php

namespace App\Security;

use App\Models\User;

interface JwtIssuerInterface
{
    public function issueAccessToken(User $user): string;
    public function verify(string $jwt): object;

}
