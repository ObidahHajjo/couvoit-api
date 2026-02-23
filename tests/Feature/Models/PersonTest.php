<?php

namespace Tests\Feature\Models;

use App\Models\Address;
use App\Models\Car;
use App\Models\CarModel;
use App\Models\City;
use App\Models\Brand;
use App\Models\Color;
use App\Models\Person;
use App\Models\Trip;
use App\Models\Type;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Throwable;

/**
 * Class PersonTest
 *
 * Feature tests for the Person Eloquent model:
 * - table name
 * - relationships (car, trips, reservations pivot)
 * - SoftDeletes behavior
 */
final class PersonTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a Car record with required dependencies (Brand/Type/CarModel/Color).
     *
     * @param string $licensePlate
     * @return Car
     */
    private function makeCar(string $licensePlate = 'AA-123-AA'): Car
    {
        $brand = Brand::query()->create(['name' => 'Brand_' . Str::random(6)]);
        $type  = Type::query()->create(['type' => 'Type_' . Str::random(6)]);

        $model = CarModel::query()->create([
            'name' => 'Model_' . Str::random(6),
            'seats' => 5,
            'brand_id' => $brand->id,
            'type_id' => $type->id,
        ]);

        $color = Color::query()->create([
            'name' => 'Color_' . Str::random(6),
            'hex_code' => '#00' . strtoupper(Str::random(4)),
        ]);

        return Car::query()->create([
            'license_plate' => $licensePlate,
            'model_id' => $model->id,
            'color_id' => $color->id,
        ]);
    }

    /**
     * Create an Address with City dependency.
     *
     * @param string $street
     * @return Address
     * @throws Throwable
     */
    private function makeAddress(string $street = 'Rue A'): Address
    {
        $city = City::query()->create([
            'name' => 'City_' . Str::random(6),
            'postal_code' => (string) random_int(10000, 99999),
        ]);

        return Address::query()->create([
            'street' => $street,
            'street_number' => '1',
            'city_id' => $city->id,
        ]);
    }

    /**
     * Create a Person record using only profile fields (no auth fields).
     *
     * Adjust keys if your Person fillable differs.
     *
     * @param array<string,mixed> $overrides
     * @return Person
     * @throws Throwable
     */
    private function makePerson(array $overrides = []): Person
    {
        $suffix = Str::lower(Str::random(8));

        $payload = array_merge([
            'first_name' => 'First',
            'last_name' => 'Last',
            'pseudo' => "pseudo_$suffix",
            'phone' => '+336' . random_int(10000000, 99999999),
            'car_id' => null,
        ], $overrides);

        return Person::query()->create($payload);
    }

    /**
     * @throws Throwable
     */
    public function test_person_uses_persons_table(): void
    {
        $this->assertSame('persons', (new Person())->getTable());
    }

    /**
     * @throws Throwable
     */
    public function test_person_belongs_to_car_when_car_id_is_set(): void
    {
        $car = $this->makeCar('CC-789-CC');
        $person = $this->makePerson(['car_id' => $car->id]);

        $person->load('car');

        $this->assertNotNull($person->car);
        $this->assertSame($car->id, $person->car->id);
        $this->assertSame('CC-789-CC', $person->car->license_plate);
    }

    /**
     * @throws Throwable
     */
    public function test_person_has_many_trips_as_driver(): void
    {
        $driver = $this->makePerson();

        $dep = $this->makeAddress('Dep Street');
        $arr = $this->makeAddress('Arr Street');

        $t1 = Trip::query()->create([
            'departure_time' => '2026-02-18 10:00:00',
            'arrival_time' => '2026-02-18 11:00:00',
            'distance_km' => 10,
            'available_seats' => 2,
            'smoking_allowed' => false,
            'departure_address_id' => $dep->id,
            'arrival_address_id' => $arr->id,
            'person_id' => $driver->id,
        ]);

        $t2 = Trip::query()->create([
            'departure_time' => '2026-02-19 10:00:00',
            'arrival_time' => '2026-02-19 12:00:00',
            'distance_km' => 120,
            'available_seats' => 3,
            'smoking_allowed' => true,
            'departure_address_id' => $dep->id,
            'arrival_address_id' => $arr->id,
            'person_id' => $driver->id,
        ]);

        $driver->refresh()->load('trips');

        $this->assertCount(2, $driver->trips);
        $this->assertTrue($driver->trips->contains($t1));
        $this->assertTrue($driver->trips->contains($t2));
    }

    /**
     * @throws Throwable
     */
    public function test_person_reservations_belongs_to_many_trips_through_pivot(): void
    {
        $driver = $this->makePerson();
        $passenger = $this->makePerson();

        $dep = $this->makeAddress('Dep Street');
        $arr = $this->makeAddress('Arr Street');

        $trip = Trip::query()->create([
            'departure_time' => '2026-02-18 10:00:00',
            'arrival_time' => '2026-02-18 12:00:00',
            'distance_km' => 50,
            'available_seats' => 2,
            'smoking_allowed' => false,
            'departure_address_id' => $dep->id,
            'arrival_address_id' => $arr->id,
            'person_id' => $driver->id,
        ]);

        $passenger->reservations()->attach($trip->id);

        $passenger->refresh()->load('reservations');

        $this->assertCount(1, $passenger->reservations);
        $this->assertSame($trip->id, $passenger->reservations->first()->id);

        $this->assertDatabaseHas('reservations', [
            'person_id' => $passenger->id,
            'trip_id' => $trip->id,
        ]);
    }

    /**
     * @throws Throwable
     */
    public function test_person_soft_delete_excludes_from_default_queries(): void
    {
        $person = $this->makePerson();

        $person->delete();

        $this->assertSoftDeleted('persons', ['id' => $person->id]);
        $this->assertNull(Person::query()->find($person->id));
        $this->assertNotNull(Person::withTrashed()->find($person->id));
    }
}
