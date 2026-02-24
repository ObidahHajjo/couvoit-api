<?php

namespace App\Services\Implementations;

use App\Exceptions\ConflictException;
use App\Exceptions\UnauthorizedException;
use App\Models\User;
use App\Repositories\Interfaces\RefreshTokenRepositoryInterface;
use App\Repositories\Interfaces\PersonRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Security\JwtIssuer;
use App\Security\JwtIssuerInterface;
use App\Services\Interfaces\AuthServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final readonly class AuthService implements AuthServiceInterface
{
    public function __construct(
        private JwtIssuerInterface $jwt,
        private RefreshTokenRepositoryInterface $refreshTokens,
        private UserRepositoryInterface $userRepository,
        private PersonRepositoryInterface $personRepository,
    ) {}

    /** @inheritDoc */
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
            return $this->issueSession($user);
        });
    }

    /** @inheritDoc */
    public function login(string $email, string $password): array
    {
        $email = strtolower(trim($email));

        /** @var User|null $user */
        $user  = $this->userRepository->findByEmail($email);
        if (!$user || !Hash::check($password, $user->password)) throw new UnauthorizedException('Invalid credentials.');
        if (!$user->is_active) throw new UnauthorizedException('Account inactive.');

        return $this->issueSession($user);
    }

    /** @inheritDoc */
    public function refresh(string $refreshToken): array
    {
        $newRefresh = bin2hex(random_bytes(32));
        $expiresAt = CarbonImmutable::now()->addSeconds((int) config('jwt.refresh_ttl', 2592000));

        $userId = $this->refreshTokens->consumeAndRotate($refreshToken, $newRefresh, $expiresAt);

        $user = $this->userRepository->findById($userId);
        if (!$user || !$user->is_active) throw new UnauthorizedException('Unauthorized.');

        $access = $this->jwt->issueAccessToken($user);

        return [
            'access_token' => $access,
            'refresh_token' => $newRefresh,
            'token_type' => 'Bearer',
            'expires_in' => (int) config('jwt.access_ttl', 900),
        ];
    }

    private function issueSession(User $user): array
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
        ];
    }
}
