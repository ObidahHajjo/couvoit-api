<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\Eloquent\TripEloquentRepository;
use App\Support\Cache\RepositoryCacheManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TripEloquentRepositoryTest extends TestCase
{
    #[Test]
    public function find_by_id_uses_trip_tag_and_key(): void
    {
        $id = 123;

        $cache = Mockery::mock(RepositoryCacheManager::class);
        $repo = new TripEloquentRepository($cache);

        $cache->shouldReceive('rememberTripById')
            ->once()
            ->with($id, Mockery::type('callable'))
            ->andReturnUsing(function ($id, $callback) {
                return null;
            });

        $res = $repo->findById($id);

        self::assertNull($res);
    }

    #[Test]
    public function search_uses_search_tag_and_key_format(): void
    {
        $paginator = Mockery::mock(LengthAwarePaginator::class);

        $cache = Mockery::mock(RepositoryCacheManager::class);
        $repo = new TripEloquentRepository($cache);

        $cache->shouldReceive('rememberTripSearch')
            ->once()
            ->with('Paris', 'Lyon', '2026-02-20', 15, 1, Mockery::type('callable'))
            ->andReturnUsing(function ($a, $b, $c, $d, $e, $callback) use ($paginator) {
                return $paginator;
            });

        request()->merge(['page' => 1]);

        $p = $repo->search('Paris', 'Lyon', '2026-02-20');

        self::assertNotNull($p);
    }
}
