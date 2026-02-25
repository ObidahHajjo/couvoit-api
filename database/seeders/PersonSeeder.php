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
            [
                'first_name' => 'admin',
                "last_name" => 'admin',
                "pseudo" => 'admin',
                "phone" => 'admin',
            ],
        ]);
    }
}
