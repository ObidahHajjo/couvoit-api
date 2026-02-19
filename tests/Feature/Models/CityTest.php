<?php

namespace Tests\Feature\Models;

use App\Models\Address;
use App\Models\City;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CityTest extends TestCase
{
    use RefreshDatabase;

    public function test_city_has_timestamps_disabled(): void
    {
        $this->assertFalse((new City())->timestamps);
    }

    public function test_city_fillable_allows_mass_assignment(): void
    {
        $city = City::query()->create(['name' => 'Marseille', 'postal_code' => '13001']);

        $this->assertDatabaseHas('cities', [
            'id' => $city->id,
            'name' => 'Marseille',
            'postal_code' => '13001',
        ]);
    }

    public function test_city_has_many_addresses(): void
    {
        $city = City::query()->create(['name' => 'Nice', 'postal_code' => '06000']);

        $a1 = Address::query()->create(['street' => 'A', 'street_number' => '1', 'city_id' => $city->id]);
        $a2 = Address::query()->create(['street' => 'B', 'street_number' => '2', 'city_id' => $city->id]);

        $city->refresh();

        $this->assertCount(2, $city->addresses);
        $this->assertTrue($city->addresses->contains($a1));
        $this->assertTrue($city->addresses->contains($a2));
    }
}
