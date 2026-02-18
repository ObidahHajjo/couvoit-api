<?php

namespace Tests\Unit\Services;

use App\Clients\Interfaces\SupabaseAuthClientInterface;
use App\Exceptions\ExternalServiceException;
use App\Repositories\Interfaces\PersonRepositoryInterface;
use App\Services\Implementations\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;
use App\Models\Person;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Class AuthServiceTest
 *
 * Unit tests for AuthService:
 * - register(): creates person if not exists, prevents duplicates, throws on bad response
 * - login(): delegates to Supabase client
 * - refresh(): delegates to Supabase client
 */
class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Mocked Supabase auth client dependency.
     *
     * @var SupabaseAuthClientInterface&MockInterface
     */
    private SupabaseAuthClientInterface $supabase;

    /**
     * Mocked Person repository dependency.
     *
     * @var PersonRepositoryInterface&MockInterface
     */
    private PersonRepositoryInterface $persons;

    /**
     * Service under test.
     *
     * @var AuthService
     */
    private AuthService $service;

    /**
     * Setup mocks and service instance.
     *
     * @return void
     *
     * @throws Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        /** @var SupabaseAuthClientInterface&MockInterface $supabase */
        $supabase = Mockery::mock(SupabaseAuthClientInterface::class);
        $this->supabase = $supabase;

        /** @var PersonRepositoryInterface&MockInterface $persons */
        $persons = Mockery::mock(PersonRepositoryInterface::class);
        $this->persons = $persons;

        $this->service = new AuthService($this->supabase, $this->persons);
    }

    /**
     * register() should create a person when not existing.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_register_creates_person_when_not_existing(): void
    {
        $email = 'john@example.com';
        $password = 'secret';
        $supabaseUserId = '00000000-0000-0000-0000-000000000111';

        $this->supabase
            ->shouldReceive('signUp')
            ->once()
            ->with($email, $password)
            ->andReturn(['user' => ['id' => $supabaseUserId, 'email' => $email]]);

        $this->persons
            ->shouldReceive('findBySupabaseUserId')
            ->once()
            ->with($supabaseUserId)
            ->andReturn(null);

        $this->persons
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $payload) use ($supabaseUserId, $email) {
                return ($payload['supabase_user_id'] ?? null) === $supabaseUserId
                    && ($payload['email'] ?? null) === $email
                    && ($payload['role_id'] ?? null) === 1
                    && ($payload['is_active'] ?? null) === true;
            }))
            ->andReturn(null);

        $res = $this->service->register($email, $password);

        $this->assertIsArray($res);
        $this->assertSame($supabaseUserId, $res['user']['id']);
    }

    /**
     * register() should NOT create a person if it already exists.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_register_does_not_create_person_when_existing(): void
    {
        $email = 'existing@example.com';
        $password = 'secret';
        $supabaseUserId = '00000000-0000-0000-0000-000000000222';

        $existingPerson = $this->makePerson($supabaseUserId, $email);

        $this->supabase
            ->shouldReceive('signUp')
            ->once()
            ->with($email, $password)
            ->andReturn(['user' => ['id' => $supabaseUserId, 'email' => $email]]);

        $this->persons
            ->shouldReceive('findBySupabaseUserId')
            ->once()
            ->with($supabaseUserId)
            ->andReturn($existingPerson);

        $this->persons
            ->shouldReceive('create')
            ->never();

        $res = $this->service->register($email, $password);

        $this->assertIsArray($res);
        $this->assertSame($supabaseUserId, $res['user']['id']);
    }

    /**
     * register() should throw ExternalServiceException if Supabase returns missing user.id.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_register_throws_when_user_id_missing(): void
    {
        $this->supabase
            ->shouldReceive('signUp')
            ->once()
            ->andReturn(['user' => ['email' => 'x@example.com']]);

        $this->expectException(ExternalServiceException::class);

        $this->service->register('x@example.com', 'secret');
    }

    /**
     * login() should delegate to Supabase client.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_login_delegates_to_supabase_client(): void
    {
        $email = 'john@example.com';
        $password = 'secret';

        $this->supabase
            ->shouldReceive('signInWithPassword')
            ->once()
            ->with($email, $password)
            ->andReturn(['access_token' => 'token']);

        $res = $this->service->login($email, $password);

        $this->assertSame('token', $res['access_token']);
    }

    /**
     * refresh() should delegate to Supabase client.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_refresh_delegates_to_supabase_client(): void
    {
        $refresh = 'refresh_token';

        $this->supabase
            ->shouldReceive('refreshToken')
            ->once()
            ->with($refresh)
            ->andReturn(['access_token' => 'new_token']);

        $res = $this->service->refresh($refresh);

        $this->assertSame('new_token', $res['access_token']);
    }

    /**
     * Seed roles with stable IDs used by Person::ROLE_USER / ROLE_ADMIN.
     *
     * @return void
     *
     * @throws Throwable
     */
    private function seedRoles(): void
    {
        DB::table('roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'user'],
            ['id' => 2, 'name' => 'admin'],
        ]);
    }

    /**
     * Create a Person model for repository return type compliance.
     *
     * @param string $supabaseUserId
     * @param string $email
     * @return Person
     *
     * @throws Throwable
     */
    private function makePerson(string $supabaseUserId, string $email): Person
    {
        $this->seedRoles();

        return Person::query()->create([
            'supabase_user_id' => $supabaseUserId,
            'email' => $email,
            'pseudo' => 'p_' . Str::random(10),
            'role_id' => 1,
            'is_active' => true,
        ]);
    }

}
