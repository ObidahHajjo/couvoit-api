<?php

namespace App\Services\Implementations;

use App\Exceptions\ExternalServiceException;
use App\Exceptions\UnauthorizedException;
use App\Repositories\Interfaces\PersonRepositoryInterface;
use App\Services\Interfaces\AuthServiceInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

readonly class AuthService implements AuthServiceInterface
{
    public function __construct(
        private PersonRepositoryInterface $personRepository
    ) {}

    /**
     * Base headers required for Supabase Auth API.
     *
     * @return array<string,string>
     */
    private function baseHeaders(): array
    {
        $anonKey = (string) config('services.supabase.anon_key');

        return [
            'apikey' => $anonKey,
            'Authorization' => 'Bearer ' . $anonKey,
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Create a preconfigured HTTP client for Supabase calls.
     */
    private function supabaseHttp(): PendingRequest
    {
        return Http::withHeaders($this->baseHeaders())
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15);
    }

    /** {@inheritDoc} */
    public function register(string $email, string $password): array
    {
        $url = rtrim((string) config('services.supabase.project_url'), '/') . '/auth/v1/signup';

        try {
            $resp = $this->supabaseHttp()->post($url, [
                'email' => $email,
                'password' => $password,
            ]);
        } catch (ConnectionException) {
            throw new ExternalServiceException('Supabase is unreachable (connection error).');
        }

        if (! $resp->successful()) throw new ExternalServiceException('Supabase registration failed. HTTP '.$resp->status());

        $data = $resp->json();
        $supabaseUserId = $data['user']['id'] ?? null;

        if ($supabaseUserId) {
            // TODO : Prevent duplicates if user registers twice
            $this->personRepository->create([
                'supabase_user_id' => $supabaseUserId,
                'email' => $email,
                'role_id' => 1,
                'is_active' => true,
            ]);
        }

        return $data;
    }

    /** {@inheritDoc} */
    public function login(string $email, string $password): array
    {
        $url = rtrim((string) config('services.supabase.project_url'), '/') .
            '/auth/v1/token?grant_type=password';

        try {
            $resp = $this->supabaseHttp()->post($url, [
                'email' => $email,
                'password' => $password,
            ]);
        } catch (ConnectionException) {
            throw new ExternalServiceException('Supabase is unreachable (connection error).');
        }

        if (! $resp->successful()) {
            if ($resp->status() === 400 || $resp->status() === 401) throw new UnauthorizedException('Invalid credentials.');
            throw new ExternalServiceException('Unable to authenticate with Supabase. HTTP '.$resp->status());
        }

        return $resp->json();
    }

    /** {@inheritDoc} */
    public function refresh(string $refreshToken): array
    {
        $url = rtrim((string) config('services.supabase.project_url'), '/') .
            '/auth/v1/token?grant_type=refresh_token';

        try {
            $resp = $this->supabaseHttp()->post($url, [
                'refresh_token' => $refreshToken,
            ]);
        } catch (ConnectionException) {
            throw new ExternalServiceException('Supabase is unreachable (connection error).');
        }

        if (! $resp->successful()) {
            if ($resp->status() === 400 || $resp->status() === 401) throw new UnauthorizedException('Invalid refresh token.');
            throw new ExternalServiceException('Unable to refresh token with Supabase. HTTP '.$resp->status());
        }

        return $resp->json();
    }
}
