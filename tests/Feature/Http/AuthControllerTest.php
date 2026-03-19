<?php

namespace Tests\Feature\Http;

use App\Services\Interfaces\AuthServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $registerUri = '/auth/register';
    private string $loginUri = '/auth/login';
    private string $refreshUri = '/auth/refresh';

    public function test_register_returns_created_with_token_resource(): void
    {
        $this->mock(AuthServiceInterface::class, function ($mock) {
            $mock->shouldReceive('register')
                ->once()
                ->with('john@example.com', 'secret12345')
                ->andReturn([
                    'access_token' => 'a',
                    'token_type' => 'Bearer',
                    'expires_in' => 3600,
                    'refresh_token' => 'r',
                    'role_id' => 1,
                    'person_id' => 1,
                ]);
        });

        $res = $this->postJson($this->registerUri, [
            'email' => 'john@example.com',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
        ]);

        $res->assertCreated();
        $res->assertJsonPath('data.access_token', 'a');
        $res->assertJsonPath('data.token_type', 'Bearer');
        $res->assertJsonPath('data.refresh_token', 'r');
        $res->assertJsonPath('data.expires_in', 3600);
    }

    public function test_login_returns_ok_with_token_resource(): void
    {
        $this->mock(AuthServiceInterface::class, function ($mock) {
            $mock->shouldReceive('login')
                ->once()
                ->with('john@example.com', 'secret12345')
                ->andReturn([
                    'access_token' => 'a',
                    'token_type' => 'Bearer',
                    'expires_in' => 3600,
                    'refresh_token' => 'r',
                    'role_id' => 1,
                    'person_id' => 1,
                ]);
        });

        $res = $this->postJson($this->loginUri, [
            'email' => 'john@example.com',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
        ]);

        $res->assertOk();

        $res->assertJsonPath('data.access_token', 'a');
        $res->assertJsonPath('data.token_type', 'Bearer');
        $res->assertJsonPath('data.refresh_token', 'r');
        $res->assertJsonPath('data.expires_in', 3600);
    }

    public function test_refresh_returns_ok_with_token_resource(): void
    {
        $this->mock(AuthServiceInterface::class, function ($mock) {
            $mock->shouldReceive('refresh')
                ->once()
                ->with('refresh_token')
                ->andReturn([
                    'access_token' => 'new_access',
                    'token_type' => 'Bearer',
                    'expires_in' => 3600,
                    'refresh_token' => 'new_refresh',
                ]);
        });

        $res = $this->call('POST', $this->refreshUri, [], [
            'refresh_token' => 'refresh_token',
        ]);

        $res->assertOk();

        $res->assertJsonPath('data.access_token', 'new_access');
        $res->assertJsonPath('data.refresh_token', 'new_refresh');
    }
}
