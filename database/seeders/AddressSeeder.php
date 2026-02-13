<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AddressSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('addresses')->insertOrIgnore([
            [
                'id' => 1,
                'street' => 'Rue de Test',
                'street_number' => '1',
                'city_id' => 1
            ],
            [
                'id' => 2,
                'street' => 'Avenue Exemple',
                'street_number' => '10',
                'city_id' => 2
            ]
        ]);
    }
}
