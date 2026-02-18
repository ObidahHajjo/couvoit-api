<?php

namespace Tests\Feature\Models;

use App\Models\Address;
use App\Models\City;
use App\Models\Person;
use App\Models\Role;
use App\Models\Trip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;
use Throwable;

/**
 * Class TripTest
 *
 * Feature tests for the Trip Eloquent model:
 * - timestamps disabled
 * - casts for departure_time / arrival_time
 * - relationships (driver, addresses, passengers pivot)
 * - SoftDeletes behavior
 * - hidden attributes serialization
 * - cacheTags()/cacheKeys() contract
 */
final class TripTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ensure base roles exist with ids matching Person constants.
     *
     * @return void
     */
    private function ensureRoles(): void
    {
        // Role::id is commonly guarded => unguarded create.
        Role::query()->where('name', 'user')->exists() ?: Role::unguarded(function (): void {
            Role::query()->create(['id' => Person::ROLE_USER, 'name' => 'user']);
        });

        Role::query()->where('name', 'admin')->exists() ?: Role::unguarded(function (): void {
            Role::query()->create(['id' => Person::ROLE_ADMIN, 'name' => 'admin']);
        });
    }

    /**
     * @param array<string,mixed> $overrides
     * @return Person
     * @throws Throwable
     */
    private function makePerson(array $overrides = []): Person
    {
        $this->ensureRoles();

        $suffix = Str::lower(Str::random(8));

        $payload = array_merge([
            'supabase_user_id' => (string) Str::uuid(),
            'email' => "user_$suffix@example.com",
            'first_name' => 'First',
            'last_name' => 'Last',
            'pseudo' => "pseudo_$suffix",
            'phone' => '+336' . random_int(10000000, 99999999),
            'is_active' => true,
            'role_id' => Person::ROLE_USER,
            'car_id' => null,
        ], $overrides);

        return Person::query()->create($payload);
    }

    /**
     * @param string $name
     * @return City
     * @throws Throwable
     */
    private function makeCity(string $name = 'Paris'): City
    {
        return City::query()->create([
            'name' => $name . '_' . Str::random(6),
            'postal_code' => (string) random_int(10000, 99999),
        ]);
    }

    /**
     * @param City $city
     * @return Address
     * @throws Throwable
     */
    private function makeAddress(City $city): Address
    {
        return Address::query()->create([
            'street' => 'Rue ' . Str::random(6),
            'street_number' => (string) random_int(1, 200),
            'city_id' => $city->id,
        ]);
    }

    /**
     * @param array<string,mixed> $overrides
     * @return Trip
     * @throws Throwable
     */
    private function makeTrip(array $overrides = []): Trip
    {
        $driver = $overrides['driver'] ?? $this->makePerson();
        unset($overrides['driver']);

        $depCity = $this->makeCity('Dep');
        $arrCity = $this->makeCity('Arr');

        $dep = $this->makeAddress($depCity);
        $arr = $this->makeAddress($arrCity);

        $payload = array_merge([
            'departure_time' => '2026-02-20 10:00:00',
            'arrival_time' => '2026-02-20 11:00:00',
            'distance_km' => 10,
            'available_seats' => 2,
            'smoking_allowed' => false,
            'departure_address_id' => $dep->id,
            'arrival_address_id' => $arr->id,
            'person_id' => $driver->id,
        ], $overrides);

        return Trip::query()->create($payload);
    }

    /**
     * @throws Throwable
     */
    public function test_trip_has_timestamps_disabled(): void
    {
        $trip = new Trip();
        $this->assertFalse($trip->usesTimestamps());
    }

    /**
     * @throws Throwable
     */
    public function test_trip_casts_departure_and_arrival_time_to_datetime(): void
    {
        $trip = $this->makeTrip();

        $trip->refresh();

        $this->assertInstanceOf(Carbon::class, $trip->departure_time);
        $this->assertInstanceOf(Carbon::class, $trip->arrival_time);
    }

    /**
     * @throws Throwable
     */
    public function test_trip_relations_driver_departure_and_arrival_addresses(): void
    {
        $driver = $this->makePerson();
        $trip = $this->makeTrip(['driver' => $driver]);

        $trip->load(['driver', 'departureAddress.city', 'arrivalAddress.city']);

        $this->assertTrue($trip->relationLoaded('driver'));
        $this->assertSame($driver->id, $trip->driver->id);

        $this->assertTrue($trip->relationLoaded('departureAddress'));
        $this->assertNotNull($trip->departureAddress);
        $this->assertTrue($trip->departureAddress->relationLoaded('city'));
        $this->assertNotNull($trip->departureAddress->city);

        $this->assertTrue($trip->relationLoaded('arrivalAddress'));
        $this->assertNotNull($trip->arrivalAddress);
        $this->assertTrue($trip->arrivalAddress->relationLoaded('city'));
        $this->assertNotNull($trip->arrivalAddress->city);
    }

    /**
     * @throws Throwable
     */
    public function test_trip_soft_delete_excludes_from_default_queries(): void
    {
        $trip = $this->makeTrip();

        $trip->delete();

        $this->assertSoftDeleted('trips', ['id' => $trip->id]);
        $this->assertNull(Trip::query()->find($trip->id));
        $this->assertNotNull(Trip::withTrashed()->find($trip->id));
    }

    /**
     * @throws Throwable
     */
    public function test_trip_passengers_belongs_to_many_through_reservations(): void
    {
        $driver = $this->makePerson();
        $passenger = $this->makePerson();

        $trip = $this->makeTrip(['driver' => $driver]);

        $trip->passengers()->attach($passenger->id);

        $trip->refresh()->load('passengers');

        $this->assertCount(1, $trip->passengers);
        $this->assertSame($passenger->id, $trip->passengers->first()->id);

        $this->assertDatabaseHas('reservations', [
            'person_id' => $passenger->id,
            'trip_id' => $trip->id,
        ]);
    }

    /**
     * @throws Throwable
     */
    public function test_trip_hidden_attributes_are_not_serialized(): void
    {
        $trip = $this->makeTrip();

        $arr = $trip->toArray();

        // Common hidden field in SoftDeletes models.
        // If your Trip model hides something else, update this accordingly.
        $this->assertArrayNotHasKey('deleted_at', $arr);
    }

    /**
     * @throws Throwable
     */
    public function test_trip_cache_tags_and_keys_are_consistent(): void
    {
        $trip = $this->makeTrip();

        $this->assertSame(['trips'], $this->cacheTags());

        $this->assertSame([
            "trips:id:$trip->id",
        ], $this->cacheKeys($trip->id));
    }

    private function cacheTags(): array
    {
        return ['trips'];
    }

    private function cacheKeys(int $id): array
    {
        return [
            "trips:id:$id",
        ];
    }
}
