<?php

namespace Database\Seeders;

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
        $types = DB::table('types')->pluck('id', 'type'); // must exist

        $models = [
            ['brand' => 'Toyota', 'name' => 'Corolla',  'type' => 'Sedan'],
            ['brand' => 'Toyota', 'name' => 'Yaris',    'type' => 'Hatchback'],
            ['brand' => 'Toyota', 'name' => 'RAV4',     'type' => 'SUV'],

            ['brand' => 'Honda',  'name' => 'Civic',    'type' => 'Sedan'],
            ['brand' => 'Honda',  'name' => 'Accord',   'type' => 'Sedan'],
            ['brand' => 'Honda',  'name' => 'CR-V',     'type' => 'SUV'],

            ['brand' => 'Ford',   'name' => 'Focus',    'type' => 'Hatchback'],
            ['brand' => 'Ford',   'name' => 'Fiesta',   'type' => 'Hatchback'],
            ['brand' => 'Ford',   'name' => 'Mustang',  'type' => 'Coupe'],

            ['brand' => 'Volkswagen', 'name' => 'Golf',   'type' => 'Hatchback'],
            ['brand' => 'Volkswagen', 'name' => 'Passat', 'type' => 'Sedan'],
            ['brand' => 'Volkswagen', 'name' => 'Polo',   'type' => 'Hatchback'],

            ['brand' => 'BMW',    'name' => '3 Series', 'type' => 'Sedan'],
            ['brand' => 'BMW',    'name' => '5 Series', 'type' => 'Sedan'],
            ['brand' => 'BMW',    'name' => 'X5',       'type' => 'SUV'],

            ['brand' => 'Audi',   'name' => 'A3',       'type' => 'Sedan'],
            ['brand' => 'Audi',   'name' => 'A4',       'type' => 'Sedan'],
            ['brand' => 'Audi',   'name' => 'Q5',       'type' => 'SUV'],

            ['brand' => 'Renault', 'name' => 'Clio',     'type' => 'Hatchback'],
            ['brand' => 'Renault', 'name' => 'Megane',   'type' => 'Hatchback'],
            ['brand' => 'Renault', 'name' => 'Captur',   'type' => 'SUV'],

            ['brand' => 'Peugeot', 'name' => '208',      'type' => 'Hatchback'],
            ['brand' => 'Peugeot', 'name' => '308',      'type' => 'Hatchback'],
            ['brand' => 'Peugeot', 'name' => '3008',     'type' => 'SUV'],

            ['brand' => 'Dacia',  'name' => 'Sandero',  'type' => 'Hatchback'],
            ['brand' => 'Dacia',  'name' => 'Duster',   'type' => 'SUV'],

            ['brand' => 'Tesla',  'name' => 'Model 3',  'type' => 'Sedan'],
            ['brand' => 'Tesla',  'name' => 'Model Y',  'type' => 'SUV'],
        ];

        $insertData = [];

        foreach ($models as $model) {
            if (! isset($brands[$model['brand']])) {
                continue;
            }

            if (! isset($types[$model['type']])) {
                continue;
            }

            $insertData[] = [
                'name' => $model['name'],
                'brand_id' => $brands[$model['brand']],
                'type_id' => $types[$model['type']],
            ];
        }

        DB::table('models')->insertOrIgnore($insertData);
    }
}
