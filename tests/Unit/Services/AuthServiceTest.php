<?php

namespace Tests\Unit\Services;

use App\Exceptions\ConflictException;
use App\Exceptions\UnauthorizedException;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Interfaces\PersonRepositoryInterface;
use App\Repositories\Interfaces\RefreshTokenRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Security\JwtIssuerInterface;
use App\Services\Implementations\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private JwtIssuerInterface $jwt;

    private RefreshTokenRepositoryInterface $refreshTokens;

    private UserRepositoryInterface $userRepository;

    private PersonRepositoryInterface $personRepository;

    private AuthService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jwt = Mockery::mock(JwtIssuerInterface::class);
        $this->refreshTokens = Mockery::mock(RefreshTokenRepositoryInterface::class);
        $this->userRepository = Mockery::mock(UserRepositoryInterface::class);
        $this->personRepository = Mockery::mock(PersonRepositoryInterface::class);

        $this->service = new AuthService(
            $this->jwt,
            $this->refreshTokens,
            $this->userRepository,
            $this->personRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function seedRoles(): void
    {
        if (! Role::query()->whereKey(1)->exists()) {
            Role::unguarded(fn () => Role::query()->create(['id' => 1, 'name' => 'user']));
        }
    }

    public function test_register_creates_user_and_person_when_not_existing(): void
    {
        $email = 'john@example.com';
        $password = 'secret';
        $hashed = Hash::make($password);

        $person = new Person;
        $person->id = 10;

        $user = new User;
        $user->id = 55;
        $user->email = $email;
        $user->password = $hashed;
        $user->is_active = true;
        $user->role_id = 1;

        $this->userRepository
            ->shouldReceive('existsByEmail')
            ->once()
            ->with(strtolower($email))
            ->andReturn(false);

        $this->personRepository
            ->shouldReceive('create')
            ->once()
            ->with([])
            ->andReturn($person);

        $this->userRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($user);

        $this->jwt
            ->shouldReceive('issueAccessToken')
            ->once()
            ->with($user)
            ->andReturn('access.jwt.token');

        $this->refreshTokens
            ->shouldReceive('store')
            ->once();

        $res = $this->service->register($email, $password);

        $this->assertIsArray($res);
        $this->assertSame('access.jwt.token', $res['access_token']);
        $this->assertSame('Bearer', $res['token_type']);
        $this->assertArrayHasKey('refresh_token', $res);
    }

    public function test_register_throws_conflict_when_user_exists(): void
    {
        $this->userRepository
            ->shouldReceive('existsByEmail')
            ->once()
            ->with('existing@example.com')
            ->andReturn(true);

        $this->expectException(ConflictException::class);

        $this->service->register('existing@example.com', 'secret');
    }

    public function test_login_throws_when_invalid_credentials(): void
    {
        $email = 'john@example.com';

        $this->userRepository
            ->shouldReceive('findByEmail')
            ->once()
            ->with($email)
            ->andReturn(null);

        $this->expectException(UnauthorizedException::class);

        $this->service->login($email, 'secret');
    }

    public function test_refresh_throws_when_inactive(): void
    {
        $oldRefresh = 'old_refresh_token';
        $userId = 99;

        $user = new User;
        $user->id = $userId;
        $user->is_active = false;

        $this->refreshTokens
            ->shouldReceive('consumeAndRotate')
            ->once()
            ->andReturn($userId);

        $this->userRepository
            ->shouldReceive('findById')
            ->once()
            ->with($userId)
            ->andReturn($user);

        $this->expectException(UnauthorizedException::class);

        $this->service->refresh($oldRefresh);
    }

    public function test_login_returns_session_when_valid(): void
    {
        $email = 'john@example.com';
        $password = 'secret';

        $user = new User;
        $user->id = 7;
        $user->person_id = 42;
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->is_active = true;

        $person = new Person;
        $person->id = 42;

        $this->userRepository
            ->shouldReceive('findByEmail')
            ->once()
            ->with($email)
            ->andReturn($user);

        $this->personRepository
            ->shouldReceive('findById')
            ->once()
            ->with(42)
            ->andReturn($person);

        $this->jwt
            ->shouldReceive('issueAccessToken')
            ->once()
            ->with($user)
            ->andReturn('access.jwt.token');

        $this->refreshTokens
            ->shouldReceive('store')
            ->once();

        $res = $this->service->login($email, $password);

        $this->assertSame('access.jwt.token', $res['access_token']);
        $this->assertSame('Bearer', $res['token_type']);
        $this->assertArrayHasKey('refresh_token', $res);
    }

    public function test_refresh_rotates_and_issues_new_access_token(): void
    {
        $oldRefresh = 'old_refresh_token';
        $userId = 99;

        $user = new User;
        $user->id = $userId;
        $user->is_active = true;

        $this->refreshTokens
            ->shouldReceive('consumeAndRotate')
            ->once()
            ->andReturn($userId);

        $this->userRepository
            ->shouldReceive('findById')
            ->once()
            ->with($userId)
            ->andReturn($user);

        $this->jwt
            ->shouldReceive('issueAccessToken')
            ->once()
            ->with($user)
            ->andReturn('new.access.token');

        $res = $this->service->refresh($oldRefresh);

        $this->assertSame('new.access.token', $res['access_token']);
        $this->assertSame('Bearer', $res['token_type']);
        $this->assertArrayHasKey('refresh_token', $res);
    }

    public function test_refresh_throws_when_user_not_found(): void
    {
        $oldRefresh = 'old_refresh_token';
        $userId = 123;

        $this->refreshTokens
            ->shouldReceive('consumeAndRotate')
            ->once()
            ->andReturn($userId);

        $this->userRepository
            ->shouldReceive('findById')
            ->once()
            ->with($userId)
            ->andReturn(null);

        $this->expectException(UnauthorizedException::class);

        $this->service->refresh($oldRefresh);
    }

    public function test_change_password_hashes_password_and_revokes_refresh_tokens(): void
    {
        $this->seedRoles();

        $person = Person::query()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'pseudo' => 'john_'.uniqid(),
            'phone' => null,
            'car_id' => null,
        ]);

        $user = User::query()->create([
            'email' => 'user_'.uniqid().'@example.com',
            'password' => Hash::make('oldsecret123'),
            'role_id' => 1,
            'is_active' => true,
            'person_id' => $person->id,
        ]);

        $this->refreshTokens
            ->shouldReceive('deleteAllByUserId')
            ->once()
            ->with($user->id);

        $this->service->changePassword($user, 'newsecret123');

        $user->refresh();

        $this->assertTrue(Hash::check('newsecret123', $user->password));
    }
}
