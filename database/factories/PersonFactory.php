<?php

namespace Database\Factories;

use App\Models\Car;
use App\Models\Person;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PersonFactory extends Factory
{
    protected $model = Person::class;

    public function definition(): array
    {
        return [
            'supabase_user_id' => Str::uuid()->toString(),
            'pseudo'           => $this->faker->unique()->userName(),
            'role_id'          => Role::factory(),
            'car_id'           => Car::factory(),
            'is_active'        => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
