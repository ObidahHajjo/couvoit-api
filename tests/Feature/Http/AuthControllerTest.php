<?php

namespace Tests\Feature\Http;

use App\Services\Interfaces\AuthServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Throwable;

/**
 * Class AuthControllerTest
 *
 * Feature tests for AuthController endpoints using real API routes:
 * - POST /register
 * - POST /login
 * - POST /refresh
 *
 * Note: AuthTokenResource is wrapped by default under "data".
 */
class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Register endpoint path.
     *
     * @var string
     */
    private string $registerUri = '/register';

    /**
     * Login endpoint path.
     *
     * @var string
     */
    private string $loginUri = '/login';

    /**
     * Refresh endpoint path.
     *
     * @var string
     */
    private string $refreshUri = '/refresh';

    /**
     * POST /register returns 201 and token payload under data.*.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_register_returns_created_with_token_resource(): void
    {
        $this->mock(AuthServiceInterface::class, function ($mock) {
            $mock->shouldReceive('register')
                ->once()
                ->with('john@example.com', 'secret12345')
                ->andReturn([
                    'access_token' => 'a',
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                    'refresh_token' => 'r',
                    'user' => ['id' => 'uuid', 'email' => 'john@example.com'],
                ]);
        });

        $res = $this->postJson($this->registerUri, [
            'email' => 'john@example.com',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
        ]);

        $res->assertCreated();
        $res->assertJsonPath('data.access_token', 'a');
        $res->assertJsonPath('data.token_type', 'bearer');
        $res->assertJsonPath('data.refresh_token', 'r');
        $res->assertJsonPath('data.expires_in', 3600);
    }

    /**
     * POST /login returns 200 and token payload under data.*.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_login_returns_ok_with_token_resource(): void
    {
        $this->mock(AuthServiceInterface::class, function ($mock) {
            $mock->shouldReceive('login')
                ->once()
                ->with('john@example.com', 'secret12345')
                ->andReturn([
                    'access_token' => 'a',
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                    'refresh_token' => 'r'
                ]);
        });

        $res = $this->postJson($this->loginUri, [
            'email' => 'john@example.com',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
        ]);

        $res->assertOk();

        $res->assertJsonPath('data.access_token', 'a');
        $res->assertJsonPath('data.token_type', 'bearer');
        $res->assertJsonPath('data.refresh_token', 'r');
        $res->assertJsonPath('data.expires_in', 3600);
    }

    /**
     * POST /refresh returns 200 and token payload under data.*.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_refresh_returns_ok_with_token_resource(): void
    {
        $this->mock(AuthServiceInterface::class, function ($mock) {
            $mock->shouldReceive('refresh')
                ->once()
                ->with('refresh_token')
                ->andReturn([
                    'access_token' => 'new_access',
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                    'refresh_token' => 'new_refresh',
                ]);
        });

        $res = $this->postJson($this->refreshUri, [
            'refresh_token' => 'refresh_token',
        ]);

        $res->assertOk();

        $res->assertJsonPath('data.access_token', 'new_access');
        $res->assertJsonPath('data.refresh_token', 'new_refresh');
    }
}
