<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Person;
use App\Repositories\Eloquent\PersonEloquentRepository;
use App\Support\Cache\RepositoryCacheManager;
use Illuminate\Support\Collection;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PersonEloquentRepositoryTest extends TestCase
{
    #[Test]
    public function all_uses_tagged_cache_and_key_persons_all(): void
    {
        $expected = new Collection([]);

        $cache = Mockery::mock(RepositoryCacheManager::class);
        $repo = new PersonEloquentRepository($cache);

        $cache->shouldReceive('rememberPersonsAll')
            ->once()
            ->andReturnUsing(function ($callback) use ($expected) {
                return $expected;
            });

        $res = $repo->all();

        self::assertSame($expected, $res);
    }

    #[Test]
    public function find_by_id_uses_tagged_cache_and_key_persons_id(): void
    {
        $id = 123;

        $person = new Person();
        $person->id = $id;

        $cache = Mockery::mock(RepositoryCacheManager::class);
        $repo = new PersonEloquentRepository($cache);

        $cache->shouldReceive('rememberPersonById')
            ->once()
            ->with($id, Mockery::type('callable'))
            ->andReturnUsing(function ($id, $callback) use ($person) {
                return $person;
            });

        $res = $repo->findById($id);

        self::assertSame($person, $res);
    }
}
