<?php

namespace Database\Factories;

use App\Models\Car;
use App\Models\CarModel;
use App\Models\Color;
use Illuminate\Database\Eloquent\Factories\Factory;

class CarFactory extends Factory
{
    protected $model = Car::class;

    public function definition(): array
    {
        return [
            'license_plate' => strtoupper($this->faker->unique()->bothify('??-###-??')),
            'model_id'      => CarModel::factory(),
            'color_id'      => Color::factory(),
        ];
    }
}
