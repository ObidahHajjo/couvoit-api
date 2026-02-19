<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\ValidationLogicException;
use App\Models\Person;
use App\Repositories\Interfaces\PersonRepositoryInterface;
use App\Repositories\Interfaces\TripRepositoryInterface;
use App\Services\Implementations\PersonService;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Throwable;

/**
 * Class PersonServiceTest
 *
 * Unit tests for PersonService use-cases and delegation logic.
 */
final class PersonServiceTest extends TestCase
{
    /**
     * @var PersonRepositoryInterface&MockInterface
     */
    private PersonRepositoryInterface $persons;

    /**
     * @var TripRepositoryInterface&MockInterface
     */
    private TripRepositoryInterface $trips;

    /**
     * @var PersonService
     */
    private PersonService $service;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var PersonRepositoryInterface&MockInterface $persons */
        $persons = Mockery::mock(PersonRepositoryInterface::class);

        /** @var TripRepositoryInterface&MockInterface $trips */
        $trips = Mockery::mock(TripRepositoryInterface::class);

        $this->persons = $persons;
        $this->trips = $trips;

        $this->service = new PersonService($this->persons, $this->trips);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function list_delegates_to_person_repository(): void
    {
        $expected = new Collection([new Person(), new Person()]);

        $this->persons->shouldReceive('all')
            ->once()
            ->andReturn($expected);

        $res = $this->service->list();

        self::assertSame($expected, $res);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function trips_as_driver_delegates_to_trip_repository(): void
    {
        $p = new Person();
        $p->id = 10;

        $expected = new Collection([]);

        $this->trips->shouldReceive('listByDriver')
            ->once()
            ->with(10)
            ->andReturn($expected);

        $res = $this->service->tripsAsDriver($p);

        self::assertSame($expected, $res);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function trips_as_passenger_delegates_to_trip_repository(): void
    {
        $p = new Person();
        $p->id = 11;

        $expected = new Collection([]);

        $this->trips->shouldReceive('listByPassenger')
            ->once()
            ->with(11)
            ->andReturn($expected);

        $res = $this->service->tripsAsPassenger($p);

        self::assertSame($expected, $res);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function update_throws_when_data_empty(): void
    {
        $this->expectException(ValidationLogicException::class);

        $p = new Person();
        $p->id = 1;

        $this->service->update($p, []);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function update_handles_status_deleted_and_updates_is_active_false(): void
    {
        $p = new Person();
        $p->id = 5;

        $this->persons->shouldReceive('update')
            ->once()
            ->with(5, ['is_active' => false]);

        $this->persons->shouldReceive('findById')
            ->once()
            ->with(5)
            ->andReturn($p);

        $res = $this->service->update($p, ['status' => 'DELETED']);

        self::assertSame($p, $res);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function update_handles_status_active_and_updates_is_active_true(): void
    {
        $p = new Person();
        $p->id = 6;

        $this->persons->shouldReceive('update')
            ->once()
            ->with(6, ['is_active' => true]);

        $this->persons->shouldReceive('findById')
            ->once()
            ->with(6)
            ->andReturn($p);

        $res = $this->service->update($p, ['status' => 'ACTIVE']);

        self::assertSame($p, $res);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function update_updates_fields_and_returns_fresh_person(): void
    {
        $p = new Person();
        $p->id = 7;

        $fresh = new Person();
        $fresh->id = 7;

        $this->persons->shouldReceive('update')
            ->once()
            ->with(7, ['first_name' => 'New']);

        $this->persons->shouldReceive('findById')
            ->once()
            ->with(7)
            ->andReturn($fresh);

        $res = $this->service->update($p, ['first_name' => 'New']);

        self::assertSame($fresh, $res);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function soft_delete_delegates_to_repository_delete(): void
    {
        $p = new Person();
        $p->id = 9;

        $this->persons->shouldReceive('delete')
            ->once()
            ->with(9);

        $this->service->softDelete($p);

        self::assertTrue(true);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function update_role_updates_and_returns_person_by_supabase_user_id(): void
    {
        $supabaseId = 'uuid-123';
        $roleId = 2;

        $expected = new Person();
        $expected->supabase_user_id = $supabaseId;

        $this->persons->shouldReceive('updateRole')
            ->once()
            ->with($supabaseId, $roleId);

        $this->persons->shouldReceive('findBySupabaseUserId')
            ->once()
            ->with($supabaseId)
            ->andReturn($expected);

        $res = $this->service->updateRole($supabaseId, $roleId);

        self::assertSame($expected, $res);
    }
}
