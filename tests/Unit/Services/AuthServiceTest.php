<?php

namespace Tests\Unit\Services;

use App\Exceptions\ConflictException;
use App\Exceptions\UnauthorizedException;
use App\Models\Person;
use App\Models\User;
use App\Repositories\Eloquent\RefreshTokenEloquentRepository;
use App\Repositories\Interfaces\PersonRepositoryInterface;
use App\Repositories\Interfaces\RefreshTokenRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Security\JwtIssuerInterface;
use App\Services\Implementations\AuthService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;
use Throwable;

/**
 * Class AuthServiceTest
 *
 * Unit tests for AuthService:
 * - register(): creates person if email not exists, throws ConflictException on duplicate
 * - login(): validates credentials and active flag
 * - refresh(): consumes & rotates refresh token then issues new access token
 */
class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var JwtIssuerInterface&MockInterface
     */
    private JwtIssuerInterface $jwt;

    /**
     * @var RefreshTokenRepositoryInterface&MockInterface
     */
    private RefreshTokenRepositoryInterface $refreshTokens;

    /**
     * @var UserRepositoryInterface&MockInterface
     */
    private UserRepositoryInterface $userRepository;

    /**
     * @var PersonRepositoryInterface&MockInterface
     */
    private PersonRepositoryInterface $personRepository;

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

        /** @var JwtIssuerInterface&MockInterface $jwt */
        $this->jwt = Mockery::mock(JwtIssuerInterface::class);

        /** @var RefreshTokenEloquentRepository&MockInterface $refreshTokens */
        $this->refreshTokens = Mockery::mock(RefreshTokenRepositoryInterface::class);

        /** @var UserRepositoryInterface&MockInterface $userRepository */
        $this->userRepository = Mockery::mock(UserRepositoryInterface::class);

        /** @var PersonRepositoryInterface&MockInterface $personRepository */
        $this->personRepository = Mockery::mock(PersonRepositoryInterface::class);

        $this->service = new AuthService(
            $this->jwt,
            $this->refreshTokens,
            $this->userRepository,
            $this->personRepository
        );
    }

    /**
     * Ensure Mockery expectations are verified/cleaned.
     *
     * @return void
     *
     * @throws Throwable
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * register() should create person + user and return session payload.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_register_creates_user_and_person_when_not_existing(): void
    {
        $email = 'john@example.com';
        $password = 'secret';
        $hashed = Hash::make($password);

        $person = new Person();
        $person->id = 10;

        $user = new User();
        $user->id = 55;
        $user->email = $email;
        $user->password = $hashed;
        $user->is_active = true;

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
            ->with(Mockery::on(function (array $data) use ($email, $person) {
                return ($data['email'] ?? null) === strtolower($email)
                    && isset($data['password'])
                    && ($data['role_id'] ?? null) === 1
                    && ($data['is_active'] ?? null) === true
                    && ($data['person_id'] ?? null) === $person->id;
            }))
            ->andReturn($user);

        $this->jwt
            ->shouldReceive('issueAccessToken')
            ->once()
            ->with($user)
            ->andReturn('access.jwt.token');

        $this->refreshTokens
            ->shouldReceive('store')
            ->once()
            ->with(
                $user->id,
                Mockery::type('string'),
                Mockery::type(CarbonImmutable::class)
            );

        $res = $this->service->register($email, $password);

        $this->assertIsArray($res);
        $this->assertSame('access.jwt.token', $res['access_token']);
        $this->assertSame('Bearer', $res['token_type']);
        $this->assertArrayHasKey('refresh_token', $res);
        $this->assertIsString($res['refresh_token']);
        $this->assertSame((int) config('jwt.access_ttl', 900), $res['expires_in']);
    }

    /**
     * register() should throw ConflictException when email already exists.
     *
     * @return void
     *
     * @throws Throwable
     */
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

    /**
     * login() should throw UnauthorizedException when credentials invalid.
     *
     * @return void
     *
     * @throws Throwable
     */
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

    /**
     * login() should throw UnauthorizedException when account inactive.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_login_throws_when_inactive(): void
    {
        $email = 'john@example.com';
        $password = 'secret';

        $user = new User();
        $user->id = 1;
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->is_active = false;

        $this->userRepository
            ->shouldReceive('findByEmail')
            ->once()
            ->with($email)
            ->andReturn($user);

        $this->expectException(UnauthorizedException::class);

        $this->service->login($email, $password);
    }

    /**
     * login() should return session payload when valid.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_login_returns_session_when_valid(): void
    {
        $email = 'john@example.com';
        $password = 'secret';

        $user = new User();
        $user->id = 7;
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->is_active = true;

        $this->userRepository
            ->shouldReceive('findByEmail')
            ->once()
            ->with($email)
            ->andReturn($user);

        $this->jwt
            ->shouldReceive('issueAccessToken')
            ->once()
            ->with($user)
            ->andReturn('access.jwt.token');

        $this->refreshTokens
            ->shouldReceive('store')
            ->once()
            ->with(
                $user->id,
                Mockery::type('string'),
                Mockery::type(CarbonImmutable::class)
            );

        $res = $this->service->login($email, $password);

        $this->assertSame('access.jwt.token', $res['access_token']);
        $this->assertSame('Bearer', $res['token_type']);
        $this->assertArrayHasKey('refresh_token', $res);
        $this->assertSame((int) config('jwt.access_ttl', 900), $res['expires_in']);
    }

    /**
     * refresh() should rotate refresh token, validate user, and return new tokens.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_refresh_rotates_and_issues_new_access_token(): void
    {
        $oldRefresh = 'old_refresh_token';
        $userId = 99;

        $user = new User();
        $user->id = $userId;
        $user->is_active = true;

        $this->refreshTokens
            ->shouldReceive('consumeAndRotate')
            ->once()
            ->with(
                $oldRefresh,
                Mockery::type('string'),
                Mockery::type(CarbonImmutable::class)
            )
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
        $this->assertIsString($res['refresh_token']);
        $this->assertSame((int) config('jwt.access_ttl', 900), $res['expires_in']);
    }

    /**
     * refresh() should throw UnauthorizedException when user not found.
     *
     * @return void
     *
     * @throws Throwable
     */
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
}
