<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\SupabaseAuth;
use App\Models\Person;
use App\Repositories\Interfaces\PersonRepositoryInterface;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Class SupabaseAuthTest
 *
 * Feature tests for SupabaseAuth middleware:
 * - missing token -> 401
 * - invalid JWT format -> 401
 * - valid token resolves person and sets auth user -> 200
 * - inactive account -> 403
 * - ExpiredException -> 401 and auth cache invalidated
 *
 * JWT and JWK parsing are alias-mocked to avoid real crypto.
 */
class SupabaseAuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Cached JWKS shape used by middleware.
     *
     * @var array<string,mixed>
     */
    private array $jwks = [
        'keys' => [
            ['kid' => 'kid1'],
        ],
    ];

    /**
     * Setup a protected route that uses the middleware.
     *
     * @return void
     *
     * @throws Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.supabase.jwks_url', 'https://example.test/jwks');

        Route::middleware(SupabaseAuth::class)->get('/protected', function (Request $request) {
            /** @var Person|null $person */
            $person = $request->attributes->get('person');

            return response()->json([
                'ok' => true,
                'person_id' => $person?->id,
            ]);
        });
    }

    /**
     * Seed roles with stable IDs used by application conventions.
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
     * Create a Person row.
     *
     * @param array<string,mixed> $overrides
     * @return Person
     *
     * @throws Throwable
     */
    private function makePerson(array $overrides = []): Person
    {
        $this->seedRoles();

        $payload = array_merge([
            'supabase_user_id' => '00000000-0000-0000-0000-000000000111',
            'email' => 'x@example.com',
            'first_name' => null,
            'last_name' => null,
            'pseudo' => 'p_' . uniqid(),
            'phone' => null,
            'is_active' => true,
            'role_id' => 1,
            'car_id' => null,
        ], $overrides);

        return Person::query()->create($payload);
    }

    /**
     * Build a fake JWT string with 3 parts.
     *
     * @return string
     *
     * @throws Throwable
     */
    private function fakeJwt(): string
    {
        // Middleware only checks "3 parts", then JWT::jsonDecode/urlSafeB64Decode are mocked anyway.
        return 'aaa.bbb.ccc';
    }

    /**
     * Alias-mock JWT + JWK for a successful decode.
     *
     * @param string $kid
     * @param string $sub
     * @param string $alg
     * @return void
     *
     * @throws Throwable
     */
    private function mockJwtSuccess(string $kid, string $sub, string $alg = 'ES256'): void
    {
        // Cache JWKS to avoid HTTP call inside getJwks()
        Cache::put('supabase:jwks', $this->jwks, 86400);

        $jwt = Mockery::mock('alias:Firebase\JWT\JWT');
        $jwk = Mockery::mock('alias:Firebase\JWT\JWK');

        // header decode (parts[0])
        $jwt->shouldReceive('urlsafeB64Decode')->andReturn('{}');
        $jwt->shouldReceive('jsonDecode')->andReturn((object) ['alg' => $alg, 'kid' => $kid]);

        // JWK parse for kid
        $jwk->shouldReceive('parseKey')->andReturn(new Key('dummy', $alg));

        // decode() payload
        $jwt->shouldReceive('decode')
            ->andReturnUsing(function (string $token, Key $key, object $headers) use ($alg, $sub) {
                $headers->alg = $alg;
                return (object) ['sub' => $sub, 'exp' => time() + 3600];
            });
    }

    /**
     * Missing Bearer token -> 401.
     *
     * @return void
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
     * @return void
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
     * Valid token resolves person, sets auth user, returns 200.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_valid_token_resolves_person_and_returns_200(): void
    {
        $person = $this->makePerson([
            'supabase_user_id' => '00000000-0000-0000-0000-000000000111',
            'is_active' => true,
        ]);

        /** @var PersonRepositoryInterface&MockInterface $repo */
        $repo = Mockery::mock(PersonRepositoryInterface::class);

        $repo->shouldReceive('findBySupabaseUserId')
            ->once()
            ->with('00000000-0000-0000-0000-000000000111')
            ->andReturn($person);

        // If cache path tries findById, allow it too.
        $repo->shouldReceive('findById')->andReturn($person);

        $this->app->instance(PersonRepositoryInterface::class, $repo);

        $this->mockJwtSuccess('kid1', '00000000-0000-0000-0000-000000000111');

        $res = $this->withHeader('Authorization', 'Bearer ' . $this->fakeJwt())
            ->getJson('/protected');

        $res->assertOk();
        $res->assertJsonPath('ok', true);
        $res->assertJsonPath('person_id', $person->id);
        $this->assertSame($person->id, auth()->id());
    }

    /**
     * Inactive account -> 403.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_inactive_account_returns_403(): void
    {
        $person = $this->makePerson([
            'supabase_user_id' => '00000000-0000-0000-0000-000000000111',
            'is_active' => false,
        ]);

        /** @var PersonRepositoryInterface&MockInterface $repo */
        $repo = Mockery::mock(PersonRepositoryInterface::class);

        $repo->shouldReceive('findBySupabaseUserId')
            ->once()
            ->andReturn($person);

        $this->app->instance(PersonRepositoryInterface::class, $repo);

        $this->mockJwtSuccess('kid1', '00000000-0000-0000-0000-000000000111');

        $res = $this->withHeader('Authorization', 'Bearer ' . $this->fakeJwt())
            ->getJson('/protected');

        $res->assertStatus(403);
        $res->assertJsonPath('error', 'Account inactive');
    }

    /**
     * Expired token -> 401 and best-effort invalidates auth cache for sub.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_expired_token_returns_401_and_invalidates_auth_cache(): void
    {
        $sub = '00000000-0000-0000-0000-000000000111';
        Cache::put('supabase:auth:' . $sub, ['token_fp' => 'x', 'person_id' => 1], 3600);

        Cache::put('supabase:jwks', $this->jwks, 86400);

        $jwt = Mockery::mock('alias:Firebase\JWT\JWT');
        $jwk = Mockery::mock('alias:Firebase\JWT\JWK');

        // urlsafeB64Decode is used for header and payload decode
        $jwt->shouldReceive('urlsafeB64Decode')
            ->andReturn('{}', '{}');

        // jsonDecode is used first for header then for payload (best effort)
        $jwt->shouldReceive('jsonDecode')
            ->andReturn(
                (object) ['alg' => 'ES256', 'kid' => 'kid1'],
                (object) ['sub' => $sub]
            );

        $jwk->shouldReceive('parseKey')->andReturn(new Key('dummy', 'ES256'));

        $jwt->shouldReceive('decode')->andThrow(new ExpiredException('expired'));

        /** @var PersonRepositoryInterface&MockInterface $repo */
        $repo = Mockery::mock(PersonRepositoryInterface::class);
        $this->app->instance(PersonRepositoryInterface::class, $repo);

        $res = $this->withHeader('Authorization', 'Bearer ' . $this->fakeJwt())
            ->getJson('/protected');

        $res->assertStatus(401);
        $res->assertJsonPath('error', 'Token expired');
        $this->assertNull(Cache::get('supabase:auth:' . $sub));
    }
}
