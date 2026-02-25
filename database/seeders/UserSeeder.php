<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::query()->insertOrIgnore([
            'email' => 'admin@cda.fr',
            "role_id" => 2,
            "password" => Hash::make("Obidah@admin123."),
            "person_id" => 1,
        ]);
    }
}
