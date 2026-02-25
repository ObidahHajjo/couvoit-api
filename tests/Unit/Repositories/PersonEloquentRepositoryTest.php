<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Person;
use App\Repositories\Eloquent\PersonEloquentRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Throwable;

/**
 * Class PersonEloquentRepositoryTest
 *
 * Unit tests for repository cache contract (tag/key usage) without needing a taggable cache store.
 */
final class PersonEloquentRepositoryTest extends TestCase
{
    /**
     * @var PersonEloquentRepository
     */
    private PersonEloquentRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = new PersonEloquentRepository();
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
    public function all_uses_tagged_cache_and_key_persons_all(): void
    {
        $expected = new Collection([]);

        $tagged = Mockery::mock();
        $tagged->shouldReceive('remember')
            ->once()
            ->with('persons:all', 3600, Mockery::type('callable'))
            ->andReturn($expected);

        Cache::shouldReceive('tags')
            ->once()
            ->with(['persons'])
            ->andReturn($tagged);

        $res = $this->repo->all();

        self::assertSame($expected, $res);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function find_by_id_uses_tagged_cache_and_key_persons_id(): void
    {
        $id = 123;

        $person = new Person();
        $person->id = $id;

        $tagged = Mockery::mock();
        $tagged->shouldReceive('remember')
            ->once()
            ->with("persons:$id", 3600, Mockery::type('callable'))
            ->andReturn($person);

        Cache::shouldReceive('tags')
            ->once()
            ->with(['persons', "person:$id"])
            ->andReturn($tagged);

        $res = $this->repo->findById($id);

        self::assertSame($person, $res);
    }
}
