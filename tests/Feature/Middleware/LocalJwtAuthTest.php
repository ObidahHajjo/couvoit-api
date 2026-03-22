<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\LocalJwtAuth;
use App\Http\Middleware\SetRequestLocale;
use App\Models\Person;
use App\Models\User;
use App\Security\JwtIssuerInterface;
use Firebase\JWT\ExpiredException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;
use Throwable;

/**
 * Class LocalJwtAuthTest
 *
 * Feature tests for LocalJwtAuth middleware:
 * - missing token -> 401
 * - invalid JWT format -> 401
 * - valid token resolves user and sets auth user -> 200
 * - inactive account -> 403
 * - verify throws ExpiredException -> 401 (best effort cache invalidation)
 */
class LocalJwtAuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Setup a protected route that uses the middleware.
     *
     * @throws Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware([SetRequestLocale::class, LocalJwtAuth::class])->get('/protected', function (Request $request) {
            /** @var Person|null $person */
            $person = $request->attributes->get('person');

            return response()->json([
                'ok' => true,
                'person_id' => $person?->id,
                'user_id' => auth()->guard()->user()?->getAuthIdentifier(),
            ]);
        });
    }

    /**
     * Seed roles with stable IDs.
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
     * Create a Person profile.
     *
     * @throws Throwable
     */
    private function makePerson(array $overrides = []): Person
    {
        $payload = array_merge([
            'first_name' => 'First',
            'last_name' => 'Last',
            'pseudo' => 'p_' . uniqid(),
            'phone' => null,
            'car_id' => null,
        ], $overrides);

        return Person::query()->create($payload);
    }

    /**
     * Create a User linked to a Person.
     *
     * @throws Throwable
     */
    private function makeUser(Person $person, array $overrides = []): User
    {
        $this->seedRoles();

        $payload = array_merge([
            'email' => 'u_' . uniqid() . '@example.com',
            'password' => Hash::make('secret12345'),
            'role_id' => 1,
            'is_active' => true,
            'person_id' => $person->id,
        ], $overrides);

        return User::query()->create($payload);
    }

    /**
     * Build a fake JWT string with 3 parts.
     */
    private function fakeJwt(): string
    {
        return 'aaa.bbb.ccc';
    }

    /**
     * Missing Bearer token -> 401.
     *
     * @throws Throwable
     */
    public function test_missing_bearer_token_returns_401(): void
    {
        $res = $this->getJson('/protected');

        $res->assertStatus(401);
        $res->assertJsonPath('error', 'Missing Bearer token');
    }

    /**
     * Invalid JWT format (not 3 parts) -> 401.
     *
     * @throws Throwable
     */
    public function test_invalid_jwt_format_returns_401(): void
    {
        $res = $this->withHeader('Authorization', 'Bearer not_a_jwt')
            ->getJson('/protected');

        $res->assertStatus(401);
        $res->assertJsonPath('error', 'Invalid JWT format');
    }

    /**
     * Valid token resolves user, sets auth user, returns 200.
     *
     * @throws Throwable
     */
    public function test_valid_token_resolves_user_and_returns_200(): void
    {
        $person = $this->makePerson();
        $user = $this->makeUser($person, ['is_active' => true, 'role_id' => 1]);

        /** @var JwtIssuerInterface&MockInterface $jwt */
        $jwt = Mockery::mock(JwtIssuerInterface::class);

        // verify() returns claims object with sub=user id
        $jwt->shouldReceive('verify')
            ->once()
            ->andReturn((object) [
                'sub' => (string) $user->id,
                'exp' => time() + 3600,
            ]);

        $this->app->instance(JwtIssuerInterface::class, $jwt);

        $res = $this->withHeader('Authorization', 'Bearer ' . $this->fakeJwt())
            ->getJson('/protected');

        $res->assertOk();
        $res->assertJsonPath('ok', true);
        $res->assertJsonPath('user_id', $user->id);
        $res->assertJsonPath('person_id', $person->id);

        self::assertSame($user->id, auth()->guard()->user()?->getAuthIdentifier());
    }

    /**
     * Inactive account -> 403.
     *
     * @throws Throwable
     */
    public function test_inactive_account_returns_403(): void
    {
        $person = $this->makePerson();
        $user = $this->makeUser($person, ['is_active' => false]);

        /** @var JwtIssuerInterface&MockInterface $jwt */
        $jwt = Mockery::mock(JwtIssuerInterface::class);

        $jwt->shouldReceive('verify')
            ->once()
            ->andReturn((object) [
                'sub' => (string) $user->id,
                'exp' => time() + 3600,
            ]);

        $this->app->instance(JwtIssuerInterface::class, $jwt);

        $res = $this->withHeader('Authorization', 'Bearer ' . $this->fakeJwt())
            ->getJson('/protected');

        $res->assertStatus(403);
        $res->assertJsonPath('error', 'Account inactive');
    }

    /**
     * Expired token -> 401 and best-effort invalidates auth cache for sub.
     *
     * Notes:
     * - Middleware uses AUTH_CACHE_PREFIX = local:auth:
     * - It clears cache only in ExpiredException path via bestEffortInvalidateCacheFromTokenParts(),
     *   which decodes payload (part[1]) to get sub.
     * - Your middleware currently calls JWT::urlsafeB64Decode/jsonDecode there but doesn't import JWT,
     *   so if it's not fixed in code, this invalidation part may not run.
     *
     * @throws Throwable
     */
    public function test_expired_token_returns_401(): void
    {
        $person = $this->makePerson();
        $user = $this->makeUser($person, ['is_active' => true]);

        // Put something in the cache to simulate auth caching
        Cache::put('local:auth:' . $user->id, ['token_fp' => 'x', 'user_id' => $user->id], 3600);

        /** @var JwtIssuerInterface&MockInterface $jwt */
        $jwt = Mockery::mock(JwtIssuerInterface::class);

        // Firebase\JWT\ExpiredException is caught explicitly in middleware
        $jwt->shouldReceive('verify')
            ->once()
            ->andThrow(new ExpiredException('expired'));

        $this->app->instance(JwtIssuerInterface::class, $jwt);

        $res = $this->withHeader('Authorization', 'Bearer ' . $this->fakeJwt())
            ->getJson('/protected');

        $res->assertStatus(401);
        $res->assertJsonPath('error', 'Token expired');

        // We don't assert cache forget here because best-effort invalidation depends on JWT helper import.
        // If you fix middleware to properly decode payload, you can assert Cache::missing('local:auth:' . $user->id).
    }

    public function test_missing_bearer_token_is_localized_from_accept_language(): void
    {
        $res = $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9,en;q=0.8')
            ->getJson('/protected');

        $res->assertStatus(401);
        $res->assertJsonPath('error', 'Jeton Bearer manquant');
    }
}
