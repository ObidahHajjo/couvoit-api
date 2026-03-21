<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Brand;
use App\Models\Car;
use App\Models\CarModel;
use App\Models\Color;
use App\Models\Person;
use App\Models\Role;
use App\Models\Trip;
use App\Models\Type;
use App\Models\User;
use App\Policies\TripPolicy;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Throwable;

/**
 * Class TripPolicyTest
 *
 * Unit tests for TripPolicy using User (auth) + Person (profile).
 */
final class TripPolicyTest extends TestCase
{
    use RefreshDatabase;

    private TripPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new TripPolicy;

        // Freeze "now()" for time-based rules.
        Carbon::setTestNow(Carbon::parse('2026-02-18 12:00:00'));

        $this->ensureRoles();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // reset
        parent::tearDown();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function before_allows_admin(): void
    {
        [$admin] = $this->makeUserWithProfile(roleId: 2);

        $res = $this->policy->before($admin);

        self::assertTrue($res === true);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function view_any_denies_inactive_user(): void
    {
        $array = $this->makeUserWithProfile(roleId: 1, userActive: false);

        $res = $this->policy->viewAny($array[0]);

        self::assertTrue($res->denied());
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function view_any_allows_active_user(): void
    {
        $array = $this->makeUserWithProfile(roleId: 1);

        $res = $this->policy->viewAny($array[0]);

        self::assertTrue($res->allowed());
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function create_denies_when_user_has_no_car(): void
    {
        $array = $this->makeUserWithProfile(roleId: 1);

        $res = $this->policy->create($array[0]);

        self::assertTrue($res->denied());
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function create_allows_when_user_is_active_and_has_car(): void
    {
        $array = $this->makeUserWithProfile(roleId: 1, withCar: true);

        $res = $this->policy->create($array[0]);

        self::assertTrue($res->allowed());
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function update_denies_when_not_driver(): void
    {
        [$user, $person] = $this->makeUserWithProfile(roleId: 1);

        $trip = new Trip;
        $trip->person_id = $person->id + 999; // not the same driver

        $res = $this->policy->update($user, $trip);

        self::assertTrue($res->denied());
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function reserve_denies_when_trip_already_started(): void
    {
        [$user, $person] = $this->makeUserWithProfile(roleId: 1);

        $trip = new Trip;
        $trip->person_id = $person->id + 999; // driver is someone else
        $trip->departure_time = Carbon::parse('2026-02-18 11:00:00'); // past

        $res = $this->policy->reserve($user, $trip, $person);

        self::assertTrue($res->denied());
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function reserve_denies_when_reserving_for_other_person(): void
    {
        [$user, $person] = $this->makeUserWithProfile(roleId: 1);
        [, $otherPerson] = $this->makeUserWithProfile(roleId: 1);

        $trip = new Trip;
        $trip->person_id = $person->id + 999;
        $trip->departure_time = Carbon::parse('2026-02-18 13:00:00'); // future

        $res = $this->policy->reserve($user, $trip, $otherPerson);

        self::assertTrue($res->denied());
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function reserve_denies_when_driver_reserves_own_trip(): void
    {
        [$user, $person] = $this->makeUserWithProfile(roleId: 1);

        $trip = new Trip;
        $trip->person_id = $person->id; // user is the driver
        $trip->departure_time = Carbon::parse('2026-02-18 13:00:00');

        $res = $this->policy->reserve($user, $trip, $person);

        self::assertTrue($res->denied());
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function reserve_allows_valid_self_reservation_before_start(): void
    {
        [$user, $person] = $this->makeUserWithProfile(roleId: 1);

        $trip = new Trip;
        $trip->person_id = $person->id + 999; // driver is someone else
        $trip->departure_time = Carbon::parse('2026-02-18 13:00:00');

        $res = $this->policy->reserve($user, $trip, $person);

        self::assertTrue($res->allowed());
    }

    /**
     * Ensure roles exist (id 1=user, 2=admin).
     */
    private function ensureRoles(): void
    {
        if (! Role::query()->where('id', 1)->exists()) {
            Role::unguarded(fn () => Role::query()->create(['id' => 1, 'name' => 'user']));
        }
        if (! Role::query()->where('id', 2)->exists()) {
            Role::unguarded(fn () => Role::query()->create(['id' => 2, 'name' => 'admin']));
        }
    }

    /**
     * @return array{0:User,1:Person}
     */
    private function makeUserWithProfile(
        int $roleId,
        bool $userActive = true,
        bool $withCar = false
    ): array {
        $suffix = Str::lower(Str::random(8));

        $carId = null;

        if ($withCar) {
            $brand = Brand::query()->create(['name' => 'brand_'.$suffix]);
            $type = Type::query()->create(['type' => 'type_'.$suffix]);

            $model = CarModel::query()->create([
                'name' => 'model_'.$suffix,
                'brand_id' => $brand->id,
                'type_id' => $type->id,
            ]);

            $color = Color::query()->create([
                'name' => 'color_'.$suffix,
                'hex_code' => '#'.strtoupper(bin2hex(random_bytes(3))),
            ]);

            $car = Car::query()->create([
                'license_plate' => strtoupper('AA-'.Str::random(3).'-ZZ'),
                'seats' => 4,
                'model_id' => $model->id,
                'color_id' => $color->id,
            ]);

            $carId = $car->id;
        }

        $person = Person::query()->create([
            'pseudo' => "p_$suffix",
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '+33600000000',
            'car_id' => $carId,
        ]);

        $user = User::query()->create([
            'email' => "u_$suffix@example.com",
            'password' => bcrypt('secret123'),
            'role_id' => $roleId,
            'is_active' => $userActive,
            'person_id' => $person->id,
        ]);

        $user->loadMissing('person');

        return [$user, $person];
    }
}
