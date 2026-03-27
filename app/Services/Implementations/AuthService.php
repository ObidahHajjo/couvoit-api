<?php

declare(strict_types=1);

/**
 * @author Covoiturage Team
 *
 * @description Default implementation of authentication and session workflows including registration, login, password management, and token refresh.
 */

namespace App\Services\Implementations;

use App\Exceptions\ConflictException;
use App\Exceptions\UnauthorizedException;
use App\Models\User;
use App\Repositories\Interfaces\PersonRepositoryInterface;
use App\Repositories\Interfaces\RefreshTokenRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Security\JwtIssuerInterface;
use App\Services\Interfaces\AuthServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

/**
 * @description Handles user authentication, session management, and password reset operations.
 */
final readonly class AuthService implements AuthServiceInterface
{
    /**
     * Create a new auth service instance.
     */
    public function __construct(
        private JwtIssuerInterface $jwt,
        private RefreshTokenRepositoryInterface $refreshTokens,
        private UserRepositoryInterface $userRepository,
        private PersonRepositoryInterface $personRepository,
    ) {}

    /**
     * @param  string  $email  User's email address
     * @param  string  $password  User's plaintext password
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int, role_id: int, person_id: int}
     *
     * @throws ConflictException If a user with the given email already exists
     */
    public function register(string $email, string $password): array
    {
        return DB::transaction(function () use ($email, $password) {

            $email = strtolower(trim($email));

            if ($this->userRepository->existsByEmail($email)) {
                throw new ConflictException('User already exists');
            }

            $person = $this->personRepository->create([]);

            $user = $this->userRepository->create([
                'email' => $email,
                'password' => Hash::make($password),
                'role_id' => 1,
                'is_active' => true,
                'person_id' => $person->id,
            ]);

            // If this throws → everything rolls back automatically
            return $this->issueSession($user, $person->id);
        });
    }

    /**
     * @param  string  $email  User's email address
     * @param  string  $password  User's plaintext password
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int, role_id: int, person_id: int}
     *
     * @throws UnauthorizedException If credentials are invalid, account is purged, or account state is invalid
     */
    public function login(string $email, string $password): array
    {
        $email = strtolower(trim($email));

        /** @var User|null $user */
        $user = $this->userRepository->findByEmail($email);
        if (! $user || ! Hash::check($password, $user->password)) {
            throw new UnauthorizedException('Invalid credentials.');
        }
        if ($user->purged_at !== null) {
            throw new UnauthorizedException('This account has been permanently deleted.');
        }
        if ($user->trashed()) {
            if ($user->deleted_at === null) {
                throw new UnauthorizedException('Invalid account state.');
            }
            if ($user->deleted_at->lt(now()->subDays(90))) {
                throw new UnauthorizedException('Restore period expired. This account has been permanently deleted.');
            }

            $this->restoreDeletedAccount($user);
            $user->refresh();
        }
        $person = $this->personRepository->findById($user->person_id);

        return $this->issueSession($user, $person->id);
    }

    /**
     * @param  string  $refreshToken  The current valid refresh token
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
     *
     * @throws UnauthorizedException If the refresh token is invalid or user is inactive
     */
    public function refresh(string $refreshToken): array
    {
        $newRefresh = bin2hex(random_bytes(32));
        $expiresAt = CarbonImmutable::now()->addSeconds((int) config('jwt.refresh_ttl', 2592000));

        $userId = $this->refreshTokens->consumeAndRotate($refreshToken, $newRefresh, $expiresAt);

        $user = $this->userRepository->findById($userId);
        if (! $user || ! $user->is_active) {
            throw new UnauthorizedException('Unauthorized.');
        }

        $access = $this->jwt->issueAccessToken($user);

        return [
            'access_token' => $access,
            'refresh_token' => $newRefresh,
            'token_type' => 'Bearer',
            'expires_in' => (int) config('jwt.access_ttl', 900),
        ];
    }

    public function logout(): void
    {
        $this->refreshTokens->deleteAllByUserId(auth()->id());
    }

    /**
     * @param  string  $email  User's email address to send password reset link
     * @return string Password reset status string
     */
    public function forgetPassword(string $email): string
    {
        return Password::sendResetLink([
            'email' => $email,
        ]);
    }

    /**
     * @param  array{email: string, password: string, password_confirmation: string, token: string}  $data  Password reset data
     * @return string Password reset status string
     */
    public function resetPassword(array $data): string
    {
        return Password::broker('users')->reset(
            [
                'email' => $data['email'],
                'password' => $data['password'],
                'password_confirmation' => $data['password_confirmation'],
                'token' => $data['token'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                event(new PasswordReset($user));
            }
        );
    }

    /**
     * @param  User  $user  The user whose password is being changed
     * @param  string  $password  The new plaintext password
     */
    public function changePassword(User $user, string $password): void
    {
        $user->forceFill([
            'password' => Hash::make($password),
        ])->save();

        $this->refreshTokens->deleteAllByUserId($user->id);
    }

    /**
     * Issue a new authenticated session payload.
     */
    private function issueSession(User $user, int $person_id): array
    {
        $access = $this->jwt->issueAccessToken($user);

        $refreshPlain = bin2hex(random_bytes(32));
        $expiresAt = CarbonImmutable::now()->addDays(30);

        $this->refreshTokens->store($user->id, $refreshPlain, $expiresAt);

        return [
            'access_token' => $access,
            'refresh_token' => $refreshPlain,
            'token_type' => 'Bearer',
            'expires_in' => (int) config('jwt.access_ttl', 900),
            'role_id' => $user->role_id,
            'person_id' => $person_id,
        ];
    }

    /**
     * Restore a previously soft-deleted account.
     */
    private function restoreDeletedAccount(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $this->personRepository->restore($user->person_id);
            $this->userRepository->restore($user);
        });
    }
}
