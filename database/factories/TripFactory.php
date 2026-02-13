<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\Person;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;

class TripFactory extends Factory
{
    protected $model = Trip::class;

    public function definition(): array
    {
        return [
            'departure_time'      => $this->faker->dateTimeBetween('+1 day', '+1 month'),
            'distance_km'         => $this->faker->randomFloat(2, 1, 1000),
            'available_seats'     => $this->faker->numberBetween(1, 4),
            'smoking_allowed'     => $this->faker->boolean(),
            'departure_address_id'=> Address::factory(),
            'arrival_address_id'  => Address::factory(),
            'person_id'           => Person::factory(), // driver
        ];
    }

    /**
     * Attach $count passengers in the reservations pivot.
     */
    public function withPassengers(int $count = 1): static
    {
        return $this->afterCreating(function (Trip $trip) use ($count) {
            $passengers = Person::factory()->count($count)->create();

            // attach into pivot table "reservations"
            $trip->passengers()->attach($passengers->pluck('id')->all());
        });
    }
}
