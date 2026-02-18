<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\Eloquent\TripEloquentRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;
use Throwable;

/**
 * Class TripEloquentRepositoryTest
 *
 * Unit tests for TripEloquentRepository cache tag/key behavior.
 */
final class TripEloquentRepositoryTest extends TestCase
{
    /**
     * @var TripEloquentRepository
     */
    private TripEloquentRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new TripEloquentRepository();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @throws Throwable
     */
    public function test_find_by_id_uses_trip_tag_and_key(): void
    {
        $id = 123;

        $tagged = Mockery::mock();
        $tagged->shouldReceive('remember')
            ->once()
            ->with("trips:$id", 3600, Mockery::type('callable'))
            ->andReturn(null);

        Cache::shouldReceive('tags')
            ->once()
            ->with(['trips', 'trip:' . $id])
            ->andReturn($tagged);

        $res = $this->repo->findById($id);

        self::assertNull($res);
    }

    /**
     * @throws Throwable
     */
    public function test_search_uses_search_tag_and_key_format(): void
    {
        // We only assert the cache tags + remember key prefix,
        // not the paginator internals.
        $tagged = Mockery::mock();
        $tagged->shouldReceive('remember')
            ->once()
            ->with(Mockery::on(function (string $key): bool {
                return str_starts_with($key, 'trips:search:');
            }), 3600, Mockery::type('callable'))
            ->andReturn(Mockery::mock(LengthAwarePaginator::class));

        Cache::shouldReceive('tags')
            ->once()
            ->with(['trips', 'trips:search'])
            ->andReturn($tagged);

        // Fake page=1 like request()->integer('page',1)
        request()->merge(['page' => 1]);

        $p = $this->repo->search('Paris', 'Lyon', '2026-02-20');

        self::assertNotNull($p);
    }
}
