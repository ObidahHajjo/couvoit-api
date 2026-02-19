<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;

class AddressFactory extends Factory
{
    protected $model = Address::class;

    public function definition(): array
    {
        return [
            'street'        => $this->faker->streetName(),
            'street_number' => $this->faker->buildingNumber(),
            'city_id'       => City::factory(),
        ];
    }
}
