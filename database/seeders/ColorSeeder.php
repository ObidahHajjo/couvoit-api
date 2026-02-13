<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ColorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('colors')->insert([
            ['name' => 'Black',  'hex_code' => '#000000'],
            ['name' => 'White',  'hex_code' => '#FFFFFF'],
            ['name' => 'Gray',   'hex_code' => '#808080'],
            ['name' => 'Silver', 'hex_code' => '#C0C0C0'],
            ['name' => 'Red',    'hex_code' => '#FF0000'],
            ['name' => 'Blue',   'hex_code' => '#0000FF'],
            ['name' => 'Green',  'hex_code' => '#008000'],
            ['name' => 'Yellow', 'hex_code' => '#FFFF00'],
            ['name' => 'Orange', 'hex_code' => '#FFA500'],
            ['name' => 'Brown',  'hex_code' => '#8B4513'],
            ['name' => 'Beige',  'hex_code' => '#F5F5DC'],
            ['name' => 'Purple', 'hex_code' => '#800080'],
            ['name' => 'Pink',   'hex_code' => '#FFC0CB'],
        ]);
    }
}
