<?php

namespace Tests\Feature\Models;

use App\Models\Address;
use App\Models\City;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_address_has_timestamps_disabled(): void
    {
        $this->assertFalse((new Address())->timestamps);
    }

    public function test_address_fillable_allows_mass_assignment(): void
    {
        $city = City::query()->create(['name' => 'Paris', 'postal_code' => '75001']);

        $address = Address::query()->create([
            'street' => 'Rue de Rivoli',
            'street_number' => '12B',
            'city_id' => $city->id,
        ]);

        $this->assertDatabaseHas('addresses', [
            'id' => $address->id,
            'street' => 'Rue de Rivoli',
            'street_number' => '12B',
            'city_id' => $city->id,
        ]);
    }

    public function test_address_belongs_to_city(): void
    {
        $city = City::query()->create(['name' => 'Lyon', 'postal_code' => '69001']);
        $address = Address::query()->create([
            'street' => 'Rue Victor Hugo',
            'street_number' => '10',
            'city_id' => $city->id,
        ]);

        $this->assertTrue($address->city()->exists());
        $this->assertSame($city->id, $address->city->id);
        $this->assertSame('Lyon', $address->city->name);
    }
}
