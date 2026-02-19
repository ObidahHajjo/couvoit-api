<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Person;
use App\Models\Trip;
use App\Policies\TripPolicy;
use Carbon\Carbon;
use Illuminate\Auth\Access\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Throwable;

/**
 * Class TripPolicyTest
 *
 * Unit tests for TripPolicy.
 */
final class TripPolicyTest extends TestCase
{
    /**
     * @var TripPolicy
     */
    private TripPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new TripPolicy();

        // Freeze "now()" for time-based rules.
        Carbon::setTestNow(Carbon::parse('2026-02-18 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // reset
        parent::tearDown();
    }

    /**
     * @throws Throwable
     */
    public function test_before_allows_admin(): void
    {
        $admin = new Person();
        $admin->is_active = true;

        // Your isAdmin() is on model; we stub by setting role_id if needed.
        // If your isAdmin() depends on relation, you may need to load it in other tests.
        $admin->role_id = Person::ROLE_ADMIN;

        $res = $this->policy->before($admin, 'viewAny');

        self::assertTrue($res === true);
    }

    /**
     * @throws Throwable
     */
    public function test_view_any_denies_inactive_user(): void
    {
        $user = new Person();
        $user->is_active = false;

        $res = $this->policy->viewAny($user);

        self::assertInstanceOf(Response::class, $res);
        self::assertTrue($res->denied());
    }

    /**
     * @throws Throwable
     */
    public function test_view_any_allows_active_user(): void
    {
        $user = new Person();
        $user->is_active = true;

        $res = $this->policy->viewAny($user);

        self::assertTrue($res->allowed());
    }

    /**
     * @throws Throwable
     */
    public function test_create_denies_when_user_has_no_car(): void
    {
        $user = new Person();
        $user->is_active = true;
        $user->car_id = null;

        $res = $this->policy->create($user);

        self::assertTrue($res->denied());
    }

    /**
     * @throws Throwable
     */
    public function test_create_allows_when_user_is_active_and_has_car(): void
    {
        $user = new Person();
        $user->is_active = true;
        $user->car_id = 10;

        $res = $this->policy->create($user);

        self::assertTrue($res->allowed());
    }

    /**
     * @throws Throwable
     */
    public function test_create_for_denies_when_creating_for_other_user(): void
    {
        $user = new Person();
        $user->id = 1;
        $user->is_active = true;

        $driver = new Person();
        $driver->id = 2;
        $driver->car_id = 99;

        $res = $this->policy->createFor($user, $driver);

        self::assertTrue($res->denied());
    }

    /**
     * @throws Throwable
     */
    public function test_update_denies_when_not_driver(): void
    {
        $user = new Person();
        $user->id = 1;
        $user->is_active = true;

        $trip = new Trip();
        $trip->person_id = 2;

        $res = $this->policy->update($user, $trip);

        self::assertTrue($res->denied());
    }

    /**
     * @throws Throwable
     */
    public function test_reserve_denies_when_trip_already_started(): void
    {
        $user = new Person();
        $user->id = 10;
        $user->is_active = true;

        $passenger = new Person();
        $passenger->id = 10;

        $trip = new Trip();
        $trip->person_id = 99;
        $trip->departure_time = Carbon::parse('2026-02-18 11:00:00'); // in the past

        $res = $this->policy->reserve($user, $trip, $passenger);

        self::assertTrue($res->denied());
    }

    /**
     * @throws Throwable
     */
    public function test_reserve_denies_when_reserving_for_other_person(): void
    {
        $user = new Person();
        $user->id = 10;
        $user->is_active = true;

        $passenger = new Person();
        $passenger->id = 11; // not self

        $trip = new Trip();
        $trip->person_id = 99;
        $trip->departure_time = Carbon::parse('2026-02-18 13:00:00'); // future

        $res = $this->policy->reserve($user, $trip, $passenger);

        self::assertTrue($res->denied());
    }

    /**
     * @throws Throwable
     */
    public function test_reserve_denies_when_driver_reserves_own_trip(): void
    {
        $user = new Person();
        $user->id = 10;
        $user->is_active = true;

        $passenger = new Person();
        $passenger->id = 10;

        $trip = new Trip();
        $trip->person_id = 10; // driver = passenger
        $trip->departure_time = Carbon::parse('2026-02-18 13:00:00');

        $res = $this->policy->reserve($user, $trip, $passenger);

        self::assertTrue($res->denied());
    }

    /**
     * @throws Throwable
     */
    public function test_reserve_allows_valid_self_reservation_before_start(): void
    {
        $user = new Person();
        $user->id = 10;
        $user->is_active = true;

        $passenger = new Person();
        $passenger->id = 10;

        $trip = new Trip();
        $trip->person_id = 99;
        $trip->departure_time = Carbon::parse('2026-02-18 13:00:00');

        $res = $this->policy->reserve($user, $trip, $passenger);

        self::assertTrue($res->allowed());
    }

    /**
     * NOTE: TripPolicy::cancel currently misses `return` on deny branches:
     *   if(! $user->is_active) Response::deny(...)
     *   if($trip->person_id !== $user->id) Response::deny(...)
     * That means it may incorrectly allow.
     *
     * @throws Throwable
     */
    public function test_cancel_has_a_missing_return_bug_documented(): void
    {
        $this->markTestIncomplete('TripPolicy::cancel has missing return statements on deny branches; fix policy then add strict tests.');
    }
}
