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
    private string $forgotPasswordUri = '/auth/forgot-password';

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

    public function test_forget_password_message_is_localized_from_accept_language(): void
    {
        $this->mock(AuthServiceInterface::class, function ($mock) {
            $mock->shouldReceive('forgetPassword')
                ->once()
                ->with('john@example.com')
                ->andReturn('passwords.sent');
        });

        $res = $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9,en;q=0.8')
            ->postJson($this->forgotPasswordUri, [
                'email' => 'john@example.com',
            ]);

        $res->assertOk();
        $res->assertJsonPath('message', 'Si un compte existe pour cet e-mail, un lien de reinitialisation a ete envoye.');
        $res->assertJsonPath('status', 'passwords.sent');
    }

    public function test_validation_errors_are_localized_from_accept_language(): void
    {
        $res = $this->withHeader('Accept-Language', 'fr-FR')
            ->postJson($this->loginUri, []);

        $res->assertStatus(422);
        $res->assertJsonPath('details', 'Les donnees fournies sont invalides.');
        $res->assertJsonPath('fields.email.0', 'Le champ e-mail est obligatoire.');
        $res->assertJsonPath('fields.password.0', 'Le champ mot de passe est obligatoire.');
    }
}
