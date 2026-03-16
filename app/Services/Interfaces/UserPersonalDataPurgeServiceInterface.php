<?php

namespace App\Services\Interfaces;

use App\Models\User;

interface UserPersonalDataPurgeServiceInterface
{
    public function purge(User $user): void;

}
