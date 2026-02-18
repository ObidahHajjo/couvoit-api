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
     * @param mixed $tags
     * @return array<int, string>
     */
    private function flattenTags(mixed $tags): array
    {
        if (!is_array($tags)) {
            return [];
        }

        $flat = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($tags));
        foreach ($it as $v) {
            if (is_string($v) && $v !== '') {
                $flat[] = $v;
            }
        }

        return array_values($flat);
    }

    /**
     * @param array<int, string> $expected
     * @return \Closure(mixed):bool
     */
    private function tagsAre(array $expected): \Closure
    {
        return function (mixed $actual) use ($expected): bool {
            return $this->flattenTags($actual) === array_values($expected);
        };
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
            ->with(Mockery::on($this->tagsAre(['trips', 'trip:' . $id])))
            ->andReturn($tagged);

        $res = $this->repo->findById($id);

        self::assertNull($res);
    }

    /**
     * @throws Throwable
     */
    public function test_search_uses_search_tag_and_key_format(): void
    {
        $tagged = Mockery::mock();
        $tagged->shouldReceive('remember')
            ->once()
            ->with(
                Mockery::on(static function (string $key): bool {
                    return str_starts_with($key, 'trips:search:');
                }),
                3600,
                Mockery::type('callable')
            )
            ->andReturn(Mockery::mock(LengthAwarePaginator::class));

        Cache::shouldReceive('tags')
            ->once()
            ->with(Mockery::on($this->tagsAre(['trips', 'trips:search'])))
            ->andReturn($tagged);

        request()->merge(['page' => 1]);

        $p = $this->repo->search('Paris', 'Lyon', '2026-02-20');

        self::assertNotNull($p);
    }
}
