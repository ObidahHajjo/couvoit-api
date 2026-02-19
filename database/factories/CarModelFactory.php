<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Type;
use Illuminate\Database\Eloquent\Factories\Factory;

class CarModelFactory extends Factory
{
    protected $model = CarModel::class;

    public function definition(): array
    {
        return [
            'name'     => strtolower($this->faker->unique()->word()),
            'seats'    => $this->faker->numberBetween(1, 9),
            'brand_id' => Brand::factory(),
            'type_id'  => Type::factory(),
        ];
    }
}
