<?php

namespace App\Services\Implementations;

use App\Models\Person;
use App\Models\User;
use App\Services\Interfaces\UserPersonalDataPurgeServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Anonymizes personally identifiable data for deleted accounts.
 */
class UserPersonalDataPurgeService implements UserPersonalDataPurgeServiceInterface
{
    /** {@inheritDoc} */
    public function purge(User $user): void
    {
        DB::transaction(function () use ($user): void {
            /** @var Person|null $person */
            $person = Person::withTrashed()->find($user->person_id);
            if ($person !== null && $person->purged_at === null) {
                $person->forceFill([
                    'first_name' => 'Deleted',
                    'last_name' => 'User',
                    'pseudo' => 'deleted_user_' . $person->id,
                    'phone' => null,
                    'purged_at' => now(),
                ])->save();
            }

            $user->forceFill([
                'email' => 'deleted_user_' . $user->id . '@example.invalid',
                'password' => Hash::make(Str::random(80)),
                'is_active' => false,
                'purged_at' => now(),
            ])->save();
        });
    }
}
