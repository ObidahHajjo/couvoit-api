<?php

namespace App\Services\Interfaces;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserServiceInterface
{
    public function listUsers(int $perPage = 15): LengthAwarePaginator;

    public function deleteUser(User $user, int $authUserId): void;
}
