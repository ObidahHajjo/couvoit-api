<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('brands')->insert([
            ['name' => 'Toyota'],
            ['name' => 'Honda'],
            ['name' => 'Ford'],
            ['name' => 'Chevrolet'],
            ['name' => 'Volkswagen'],
            ['name' => 'BMW'],
            ['name' => 'Mercedes-Benz'],
            ['name' => 'Audi'],
            ['name' => 'Renault'],
            ['name' => 'Peugeot'],
            ['name' => 'Citroën'],
            ['name' => 'Fiat'],
            ['name' => 'Hyundai'],
            ['name' => 'Kia'],
            ['name' => 'Nissan'],
            ['name' => 'Mazda'],
            ['name' => 'Volvo'],
            ['name' => 'Skoda'],
            ['name' => 'Seat'],
            ['name' => 'Opel'],
            ['name' => 'Dacia'],
            ['name' => 'Tesla'],
            ['name' => 'Jeep'],
            ['name' => 'Land Rover'],
            ['name' => 'Porsche'],
        ]);
    }
}
