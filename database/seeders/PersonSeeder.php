<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PersonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('persons')->insertOrIgnore([
            ['email' => 'admin@cda.fr', "role_id" => 2, "supabase_user_id" => "5acf29ea-a6db-45b0-a24b-1ab9488c1691"],
        ]);
    }
}
