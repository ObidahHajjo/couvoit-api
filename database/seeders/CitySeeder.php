<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('cities')->insertOrIgnore([
            ['id' => 1, 'name' => 'Paris', 'postal_code' => '75000'],
            ['id' => 2, 'name' => 'Lyon', 'postal_code' => '69000'],
            ['id' => 3, 'name' => 'Marseille', 'postal_code' => '13000']
        ]);
    }
}
