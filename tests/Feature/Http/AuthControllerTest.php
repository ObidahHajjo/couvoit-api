<?php

namespace Tests\Feature\Http;

use App\Http\Middleware\LocalJwtAuth;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Services\Interfaces\AuthServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $registerUri = '/auth/register';

    private string $loginUri = '/auth/login';

    private string $refreshUri = '/auth/refresh';

    private string $forgotPasswordUri = '/auth/forgot-password';

    private string $changePasswordUri = '/auth/change-password';

    private function seedRoles(): void
    {
        if (! Role::query()->whereKey(1)->exists()) {
            Role::unguarded(fn () => Role::query()->create(['id' => 1, 'name' => 'user']));
        }

        if (! Role::query()->whereKey(2)->exists()) {
            Role::unguarded(fn () => Role::query()->create(['id' => 2, 'name' => 'admin']));
        }
    }

    private function makeUser(string $password = 'secret12345'): User
    {
        $this->seedRoles();

        $person = Person::query()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'pseudo' => 'john_'.uniqid(),
            'phone' => null,
            'car_id' => null,
        ]);

        return User::query()->create([
            'email' => 'user_'.uniqid().'@example.com',
            'password' => Hash::make($password),
            'role_id' => 1,
            'is_active' => true,
            'person_id' => $person->id,
        ]);
    }

    public function test_register_returns_created_with_token_resource(): void
    {
        $this->mock(AuthServiceInterface::class, function ($mock) {
            $mock->shouldReceive('register')
                ->once()
                ->with('john@example.com', 'Secret123!')
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
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
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

        $setCookieHeaders = $res->headers->getCookies();
        $accessCookie = collect($setCookieHeaders)->first(fn ($cookie) => $cookie->getName() === 'access_token');
        $refreshCookie = collect($setCookieHeaders)->first(fn ($cookie) => $cookie->getName() === 'refresh_token');

        $this->assertNotNull($accessCookie);
        $this->assertNotNull($refreshCookie);
        $this->assertFalse($accessCookie->isSecure());
        $this->assertFalse($refreshCookie->isSecure());
        $this->assertSame('lax', strtolower($accessCookie->getSameSite() ?? ''));
        $this->assertSame('lax', strtolower($refreshCookie->getSameSite() ?? ''));
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

    public function test_validation_errors_are_localized_from_normalized_arabic_accept_language(): void
    {
        $res = $this->withHeader('Accept-Language', 'ar-SA,ar;q=0.9,en;q=0.8')
            ->postJson($this->loginUri, []);

        $res->assertStatus(422);
        $res->assertJsonPath('details', 'البيانات المقدمة غير صالحة.');
        $res->assertJsonPath('fields.email.0', 'حقل البريد الإلكتروني مطلوب.');
        $res->assertJsonPath('fields.password.0', 'حقل كلمة المرور مطلوب.');
    }

    public function test_change_password_is_protected(): void
    {
        $res = $this->postJson($this->changePasswordUri, [
            'current_password' => 'secret12345',
            'password' => 'newsecret123',
            'password_confirmation' => 'newsecret123',
        ]);

        $res->assertStatus(401);
        $res->assertJsonPath('error', 'Missing Bearer token');
    }

    public function test_change_password_updates_password_for_authenticated_user(): void
    {
        $user = $this->makeUser();

        $this->withoutMiddleware(LocalJwtAuth::class);

        $this->mock(AuthServiceInterface::class, function ($mock) use ($user) {
            $mock->shouldReceive('changePassword')
                ->once()
                ->withArgs(function (User $passedUser, string $password) use ($user): bool {
                    return $passedUser->is($user) && $password === 'Newsecret123!';
                });
        });

        $res = $this->actingAs($user)->postJson($this->changePasswordUri, [
            'current_password' => 'secret12345',
            'password' => 'Newsecret123!',
            'password_confirmation' => 'Newsecret123!',
        ]);

        $res->assertOk();
        $res->assertJsonPath('message', 'Password changed successfully.');
    }

    public function test_change_password_validates_current_password(): void
    {
        $user = $this->makeUser();

        $this->withoutMiddleware(LocalJwtAuth::class);

        $this->mock(AuthServiceInterface::class, function ($mock) {
            $mock->shouldNotReceive('changePassword');
        });

        $res = $this->actingAs($user)->postJson($this->changePasswordUri, [
            'current_password' => 'wrong-password',
            'password' => 'newsecret123',
            'password_confirmation' => 'newsecret123',
        ]);

        $res->assertStatus(422);
        $res->assertJsonPath('error', 'VALIDATION_ERROR');
        $this->assertArrayHasKey('current_password', $res->json('fields'));
    }

    public function test_register_validates_password_complexity(): void
    {
        $this->mock(AuthServiceInterface::class, function ($mock) {
            $mock->shouldNotReceive('register');
        });

        $res = $this->postJson($this->registerUri, [
            'email' => 'john@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $res->assertStatus(422);
        $res->assertJsonPath('error', 'VALIDATION_ERROR');
        $this->assertArrayHasKey('password', $res->json('fields'));
    }

    public function test_change_password_validates_password_complexity(): void
    {
        $user = $this->makeUser();

        $this->withoutMiddleware(LocalJwtAuth::class);

        $this->mock(AuthServiceInterface::class, function ($mock) {
            $mock->shouldNotReceive('changePassword');
        });

        $res = $this->actingAs($user)->postJson($this->changePasswordUri, [
            'current_password' => 'secret12345',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $res->assertStatus(422);
        $res->assertJsonPath('error', 'VALIDATION_ERROR');
        $this->assertArrayHasKey('password', $res->json('fields'));
    }
}
