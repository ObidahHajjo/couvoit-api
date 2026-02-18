<?php

namespace Tests\Feature\Models;

use App\Models\Address;
use App\Models\Brand;
use App\Models\Car;
use App\Models\CarModel;
use App\Models\City;
use App\Models\Color;
use App\Models\Person;
use App\Models\Role;
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
 * - fillable / guarded behavior
 * - relationships (role, car, trips, reservations pivot)
 * - SoftDeletes behavior
 * - isAdmin() role check
 * - cacheTags() / cacheKeys() contract
 */
class PersonTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ensure base roles exist with the ids expected by Person constants.
     *
     * @return void
     */
    private function ensureRoles(): void
    {
        $this->makeRole('user', Person::ROLE_USER);
        $this->makeRole('admin', Person::ROLE_ADMIN);
    }

    /**
     * Create or fetch a Role record safely (no unique name collisions).
     * Also supports deterministic ids even if Role::id is guarded.
     *
     * @param string $name
     * @param int|null $id
     * @return Role
     */
    private function makeRole(string $name = 'user', ?int $id = null): Role
    {
        /** @var Role|null $existing */
        $existing = Role::query()->where('name', $name)->first();
        if ($existing) {
            return $existing;
        }

        if ($id !== null) {
            /** @var Role $role */
            $role = Role::unguarded(fn () => Role::query()->create(['id' => $id, 'name' => $name]));
            return $role;
        }

        return Role::query()->create(['name' => $name]);
    }

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
     * Create a Person record with minimum required attributes (based on your schema/model).
     *
     * @param array<string, mixed> $overrides Attribute overrides
     * @return Person
     * @throws Throwable
     */
    private function makePerson(array $overrides = []): Person
    {
        $this->ensureRoles();

        $roleId = $overrides['role_id'] ?? Person::ROLE_USER;

        $payload = array_merge([
            'supabase_user_id' => (string) Str::uuid(),
            'email' => 'user_' . Str::random(8) . '@example.com',
            'first_name' => 'First',
            'last_name' => 'Last',
            'pseudo' => 'pseudo_' . Str::random(8),
            'phone' => '+336' . random_int(10000000, 99999999),
            'is_active' => true,
            'role_id' => $roleId,
            'car_id' => null,
        ], $overrides);

        return Person::query()->create($payload);
    }

    /**
     * Assert Person uses the expected DB table ("persons").
     *
     * @return void
     */
    public function test_person_uses_persons_table(): void
    {
        $this->assertSame('persons', (new Person())->getTable());
    }

    /**
     * Assert fillable fields allow mass assignment and guarded id can't be overwritten.
     *
     * @return void
     */
    public function test_person_fillable_allows_mass_assignment_and_id_is_guarded(): void
    {
        $this->ensureRoles();

        $role = Role::query()->whereKey(Person::ROLE_USER)->firstOrFail();
        $car  = $this->makeCar('BB-456-BB');

        $person = Person::query()->create([
            'id' => 999999, // attempt overwrite PK

            'supabase_user_id' => (string) Str::uuid(),
            'email' => 'john.doe@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'pseudo' => 'john_doe_' . Str::random(6),
            'phone' => '+33600000000',
            'is_active' => true,
            'role_id' => $role->id,
            'car_id' => $car->id,
        ]);

        $this->assertNotSame(999999, $person->id);

        $this->assertDatabaseHas('persons', [
            'id' => $person->id,
            'email' => 'john.doe@example.com',
            'role_id' => $role->id,
            'car_id' => $car->id,
            'is_active' => true,
        ]);
    }

    /**
     * Assert Person belongs to Role.
     *
     * @return void
     * @throws Throwable
     */
    public function test_person_belongs_to_role(): void
    {
        $this->ensureRoles();

        $role = Role::query()->where('name', 'user')->firstOrFail();
        $person = $this->makePerson(['role_id' => $role->id]);

        $this->assertTrue($person->role()->exists());
        $this->assertSame($role->id, $person->role->id);
        $this->assertSame('user', $person->role->name);
    }

    /**
     * Assert Person belongs to Car (nullable relation).
     *
     * @return void
     * @throws Throwable
     */
    public function test_person_belongs_to_car_when_car_id_is_set(): void
    {
        $car = $this->makeCar('CC-789-CC');
        $person = $this->makePerson(['car_id' => $car->id]);

        $this->assertTrue($person->car()->exists());
        $this->assertSame($car->id, $person->car->id);
        $this->assertSame('CC-789-CC', $person->car->license_plate);
    }

    /**
     * Assert Person has many Trips (driver trips).
     *
     * @return void
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

        $driver->refresh();

        $this->assertCount(2, $driver->trips);
        $this->assertTrue($driver->trips->contains($t1));
        $this->assertTrue($driver->trips->contains($t2));
    }

    /**
     * Assert Person belongsToMany Trips via reservations (passenger trips).
     *
     * @return void
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

        $passenger->refresh();

        $this->assertCount(1, $passenger->reservations);
        $this->assertSame($trip->id, $passenger->reservations->first()->id);

        $this->assertDatabaseHas('reservations', [
            'person_id' => $passenger->id,
            'trip_id' => $trip->id,
        ]);
    }

    /**
     * Assert SoftDeletes hides deleted records from default queries.
     *
     * @return void
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

    /**
     * Assert isAdmin() returns true only when role_id equals ROLE_ADMIN.
     *
     * @return void
     * @throws Throwable
     */
    public function test_person_is_admin_checks_role_id_constant(): void
    {
        $this->ensureRoles();

        $user  = $this->makePerson(['role_id' => Person::ROLE_USER]);
        $admin = $this->makePerson(['role_id' => Person::ROLE_ADMIN]);

        $this->assertFalse($user->isAdmin());
        $this->assertTrue($admin->isAdmin());
    }

    /**
     * Assert cacheTags() returns the expected tag list.
     *
     * @return void
     * @throws Throwable
     */
    public function test_person_cache_tags_contract(): void
    {
        $person = $this->makePerson();

        $this->assertSame(['persons'], $person->cacheTags());
    }

    /**
     * Assert cacheKeys() returns keys based on id and supabase_user_id.
     *
     * @return void
     * @throws Throwable
     */
    public function test_person_cache_keys_contract(): void
    {
        $person = $this->makePerson([
            'supabase_user_id' => '00000000-0000-0000-0000-000000000999',
        ]);

        $this->assertSame([
            "persons:id:$person->id",
            "persons:supabase:00000000-0000-0000-0000-000000000999",
        ], $person->cacheKeys());
    }

    /**
     * Assert the constants match expected values.
     *
     * @return void
     */
    public function test_person_role_constants_are_stable(): void
    {
        $this->assertSame(1, Person::ROLE_USER);
        $this->assertSame(2, Person::ROLE_ADMIN);
    }
}
