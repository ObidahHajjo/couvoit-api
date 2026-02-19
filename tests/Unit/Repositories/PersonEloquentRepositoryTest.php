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
        $person->supabase_user_id = null; // avoid warming supabase cache branch

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

    /**
     * @throws Throwable
     */
    #[Test]
    public function find_by_supabase_user_id_uses_tagged_cache_and_key_persons_supabase_uuid_and_warms_id_cache(): void
    {
        $uuid = 'abc-uuid';

        $person = new Person();
        $person->id = 77;
        $person->supabase_user_id = $uuid;

        // 1) Main remember on supabase tag
        $taggedSupabase = Mockery::mock();
        $taggedSupabase->shouldReceive('remember')
            ->once()
            ->with("persons:supabase:$uuid", 3600, Mockery::type('callable'))
            ->andReturn($person);

        // 2) Warm ID cache on person tag
        $taggedPerson = Mockery::mock();
        $taggedPerson->shouldReceive('put')
            ->once()
            ->with("persons:$person->id",  $person, 3600)
            ->andReturnTrue();

        Cache::shouldReceive('tags')
            ->once()
            ->with(['persons', "supabase:$uuid"])
            ->andReturn($taggedSupabase);

        Cache::shouldReceive('tags')
            ->once()
            ->with(['persons', "person:$person->id"])
            ->andReturn($taggedPerson);

        $res = $this->repo->findBySupabaseUserId($uuid);

        self::assertSame($person, $res);
    }
}
