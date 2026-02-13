<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CarModelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $brands = DB::table('brands')->pluck('id', 'name');
        $types  = DB::table('types')->pluck('id', 'type'); // must exist

        $models = [
            ['brand' => 'Toyota', 'name' => 'Corolla',  'seats' => 5, 'type' => 'Sedan'],
            ['brand' => 'Toyota', 'name' => 'Yaris',    'seats' => 5, 'type' => 'Hatchback'],
            ['brand' => 'Toyota', 'name' => 'RAV4',     'seats' => 5, 'type' => 'SUV'],

            ['brand' => 'Honda',  'name' => 'Civic',    'seats' => 5, 'type' => 'Sedan'],
            ['brand' => 'Honda',  'name' => 'Accord',   'seats' => 5, 'type' => 'Sedan'],
            ['brand' => 'Honda',  'name' => 'CR-V',     'seats' => 5, 'type' => 'SUV'],

            ['brand' => 'Ford',   'name' => 'Focus',    'seats' => 5, 'type' => 'Hatchback'],
            ['brand' => 'Ford',   'name' => 'Fiesta',   'seats' => 5, 'type' => 'Hatchback'],
            ['brand' => 'Ford',   'name' => 'Mustang',  'seats' => 4, 'type' => 'Coupe'],

            ['brand' => 'Volkswagen', 'name' => 'Golf',   'seats' => 5, 'type' => 'Hatchback'],
            ['brand' => 'Volkswagen', 'name' => 'Passat', 'seats' => 5, 'type' => 'Sedan'],
            ['brand' => 'Volkswagen', 'name' => 'Polo',   'seats' => 5, 'type' => 'Hatchback'],

            ['brand' => 'BMW',    'name' => '3 Series', 'seats' => 5, 'type' => 'Sedan'],
            ['brand' => 'BMW',    'name' => '5 Series', 'seats' => 5, 'type' => 'Sedan'],
            ['brand' => 'BMW',    'name' => 'X5',       'seats' => 5, 'type' => 'SUV'],

            ['brand' => 'Audi',   'name' => 'A3',       'seats' => 5, 'type' => 'Sedan'],
            ['brand' => 'Audi',   'name' => 'A4',       'seats' => 5, 'type' => 'Sedan'],
            ['brand' => 'Audi',   'name' => 'Q5',       'seats' => 5, 'type' => 'SUV'],

            ['brand' => 'Renault','name' => 'Clio',     'seats' => 5, 'type' => 'Hatchback'],
            ['brand' => 'Renault','name' => 'Megane',   'seats' => 5, 'type' => 'Hatchback'],
            ['brand' => 'Renault','name' => 'Captur',   'seats' => 5, 'type' => 'SUV'],

            ['brand' => 'Peugeot','name' => '208',      'seats' => 5, 'type' => 'Hatchback'],
            ['brand' => 'Peugeot','name' => '308',      'seats' => 5, 'type' => 'Hatchback'],
            ['brand' => 'Peugeot','name' => '3008',     'seats' => 5, 'type' => 'SUV'],

            ['brand' => 'Dacia',  'name' => 'Sandero',  'seats' => 5, 'type' => 'Hatchback'],
            ['brand' => 'Dacia',  'name' => 'Duster',   'seats' => 5, 'type' => 'SUV'],

            ['brand' => 'Tesla',  'name' => 'Model 3',  'seats' => 5, 'type' => 'Sedan'],
            ['brand' => 'Tesla',  'name' => 'Model Y',  'seats' => 5, 'type' => 'SUV'],
        ];

        $insertData = [];

        foreach ($models as $model) {
            if (!isset($brands[$model['brand']])) {
                continue;
            }

            if (!isset($types[$model['type']])) {
                continue;
            }

            $insertData[] = [
                'name' => $model['name'],
                'seats' => $model['seats'],
                'brand_id' => $brands[$model['brand']],
                'type_id' => $types[$model['type']]
            ];
        }

        DB::table('models')->insertOrIgnore($insertData);
    }
}
