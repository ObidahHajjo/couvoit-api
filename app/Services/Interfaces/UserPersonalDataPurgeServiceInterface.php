<?php

namespace App\Services\Interfaces;

use App\Models\User;

/**
 * Contract for anonymizing deleted user accounts.
 */
interface UserPersonalDataPurgeServiceInterface
{
    /**
     * Irreversibly anonymize personal data for a deleted user.
     *
     * @param User $user Deleted user whose personal data should be purged.
     *
     * @return void
     */
    public function purge(User $user): void;
}
