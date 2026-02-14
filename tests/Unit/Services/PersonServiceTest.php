<?php

namespace Tests\Unit\Services;

use App\Models\Person;
use App\Repositories\Interfaces\PersonRepositoryInterface;
use App\Services\Implementations\PersonService;
use Mockery;
use Tests\TestCase;

class PersonServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_list_calls_repository_all(): void
    {
        $repo = Mockery::mock(PersonRepositoryInterface::class);
        $repo->shouldReceive('all')->once()->andReturn(collect(['x']));

        $service = new PersonService($repo);

        $result = $service->list();

        $this->assertEquals(collect(['x']), $result);
    }

    public function test_show_calls_repository_find_by_id(): void
    {
        $repo = Mockery::mock(PersonRepositoryInterface::class);

        $person = new Person();
        $person->id = 10;

        $repo->shouldReceive('findById')->once()->with(10)->andReturn($person);

        $service = new PersonService($repo);

        $result = $service->show(10);

        $this->assertSame($person, $result);
    }

    public function test_create_calls_repository_create(): void
    {
        $repo = Mockery::mock(PersonRepositoryInterface::class);

        $payload = ['email' => 'a@b.com', 'role_id' => 1, 'is_active' => true];

        $created = new Person($payload);
        $created->id = 1;

        $repo->shouldReceive('create')->once()->with($payload)->andReturn($created);

        $service = new PersonService($repo);

        $result = $service->create($payload);

        $this->assertSame($created, $result);
    }

    public function test_update_calls_repository_update(): void
    {
        $repo = Mockery::mock(PersonRepositoryInterface::class);

        $repo->shouldReceive('update')->once()->with(5, ['pseudo' => 'new']);

        $service = new PersonService($repo);

        $service->update(5, ['pseudo' => 'new']);

        $this->assertTrue(true); // reached here
    }

    public function test_delete_calls_repository_delete(): void
    {
        $repo = Mockery::mock(PersonRepositoryInterface::class);

        $repo->shouldReceive('delete')->once()->with(7);

        $service = new PersonService($repo);

        $service->delete(7);

        $this->assertTrue(true);
    }

    public function test_get_or_create_for_supabase_builds_default_payload_and_calls_get_or_create(): void
    {
        $repo = Mockery::mock(PersonRepositoryInterface::class);

        $email = 'user@example.com';

        $expectedData = [
            'email'     => $email,
            'role_id'   => 1,
            'is_active' => true,
        ];

        $person = new Person($expectedData);
        $person->id = 99;

        $repo->shouldReceive('getOrCreate')
            ->once()
            ->with($email, $expectedData)
            ->andReturn($person);

        $service = new PersonService($repo);

        $result = $service->getOrCreateForSupabase($email);

        $this->assertSame($person, $result);
    }

    public function test_update_my_profile_unsets_supabase_user_id_before_update(): void
    {
        $repo = Mockery::mock(PersonRepositoryInterface::class);

        $me = new Person();
        $me->id = 123;

        $incoming = [
            'supabase_user_id' => 'should_be_removed',
            'pseudo' => 'changed',
        ];

        $expected = [
            'pseudo' => 'changed',
        ];

        $repo->shouldReceive('update')->once()->with(123, $expected);

        $service = new PersonService($repo);

        $service->updateMyProfile($me, $incoming);

        $this->assertTrue(true);
    }
}
