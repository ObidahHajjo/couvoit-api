<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('types')->insertOrIgnore([
            ['id' => 1, 'type' => 'Sedan'],
            ['id' => 2, 'type' => 'SUV'],
            ['id' => 3, 'type' => 'Coupe'],
            ['id' => 4, 'type' => 'Hatchback'],
        ]);
    }
}
