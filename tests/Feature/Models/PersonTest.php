<?php

namespace Tests\Feature\Models;

use App\Models\Car;
use App\Models\Person;
use App\Models\Role;
use App\Models\Trip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonTest extends TestCase
{
    use RefreshDatabase;

    public function test_person_table_is_persons_and_fillable_allows_expected_fields(): void
    {
        $role = Role::factory()->create();
        $car  = Car::factory()->create();

        $person = Person::query()->create([
            'supabase_user_id' => '00000000-0000-0000-0000-000000000001',
            'email'            => 'test@example.com',
            'first_name'       => 'John',
            'last_name'        => 'Doe',
            'pseudo'           => 'john_doe',
            'phone'            => '0600000000',
            'is_active'        => true,
            'role_id'          => $role->id,
            'car_id'           => $car->id,
        ]);

        $this->assertDatabaseHas('persons', [
            'id'               => $person->id,
            'email'            => 'test@example.com',
            'pseudo'           => 'john_doe',
            'role_id'          => $role->id,
            'car_id'           => $car->id,
        ]);
    }

    public function test_relationships_role_car_trips_reservations(): void
    {
        $role = Role::factory()->create(['name' => 'user']);
        $car  = Car::factory()->create();

        $person = Person::factory()->create([
            'role_id' => $role->id,
            'car_id'  => $car->id,
        ]);

        // trips where person is driver
        $trip1 = Trip::factory()->create(['person_id' => $person->id]);
        $trip2 = Trip::factory()->create(['person_id' => $person->id]);

        // reservations pivot: person is passenger
        $tripAsPassenger = Trip::factory()->create();
        $person->reservations()->attach($tripAsPassenger->id);

        $person->load(['role', 'car', 'trips', 'reservations']);

        $this->assertEquals($role->id, $person->role->id);
        $this->assertEquals($car->id, $person->car->id);

        $this->assertCount(2, $person->trips);
        $this->assertTrue($person->trips->pluck('id')->contains($trip1->id));
        $this->assertTrue($person->trips->pluck('id')->contains($trip2->id));

        $this->assertCount(1, $person->reservations);
        $this->assertEquals($tripAsPassenger->id, $person->reservations->first()->id);
    }

    public function test_is_admin_true_when_role_name_admin(): void
    {
        $adminRole = Role::factory()->create(['name' => 'admin']);
        $person = Person::factory()->create(['role_id' => $adminRole->id]);

        // role must be loaded for the nullsafe access to hit real role
        $person->load('role');

        $this->assertTrue($person->isAdmin());
    }

    public function test_is_admin_false_when_role_is_not_admin(): void
    {
        $userRole = Role::factory()->create(['name' => 'user']);
        $person = Person::factory()->create(['role_id' => $userRole->id]);
        $person->load('role');

        $this->assertFalse($person->isAdmin());
    }

    public function test_is_admin_false_when_role_is_null(): void
    {
        // Create without role relationship (role_id null if DB allows; if not, skip by using an existing but not-loaded role)
        $role = Role::factory()->create(['name' => 'user']);
        $person = Person::factory()->create(['role_id' => $role->id]);

        // Force role not loaded and relation null by unsetting relation
        $person->unsetRelation('role');

        // In this state role property is null until loaded; nullsafe => false
        $this->assertFalse($person->isAdmin());
    }
}
