<?php

namespace App\Clients\Implementations;

use App\Clients\Interfaces\SupabaseAuthClientInterface;
use App\Exceptions\ConflictException;
use App\Exceptions\ExternalServiceException;
use App\Exceptions\UnauthorizedException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final readonly class SupabaseAuthClient implements SupabaseAuthClientInterface
{
    /**
     * Build default headers required by Supabase Auth API.
     *
     * @return array<string, string>
     */
    private function baseHeaders(): array
    {
        $anonKey = (string)config('services.supabase.anon_key');

        return [
            'apikey' => $anonKey,
            'Authorization' => 'Bearer ' . $anonKey,
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Create configured HTTP client for Supabase requests.
     */
    private function http(): PendingRequest
    {
        return Http::withHeaders($this->baseHeaders())
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15);
    }

    /**
     * Resolve Supabase project base URL.
     */
    private function baseUrl(): string
    {
        return rtrim((string)config('services.supabase.project_url'), '/');
    }

    /** @inheritDoc */
    public function signUp(string $email, string $password): array
    {
        $url = $this->baseUrl() . '/auth/v1/signup';

        try {
            $resp = $this->http()->post($url, [
                'email' => $email,
                'password' => $password,
            ]);
        } catch (ConnectionException) {
            throw new ExternalServiceException('Supabase is unreachable (connection error).');
        }

        if (!$resp->successful()) {
            if (str_contains($resp->body(), 'user_already_exists')) throw new ConflictException('User already exists');

            throw new ExternalServiceException('Supabase registration failed. HTTP ' . $resp->status());
        }

        return (array)$resp->json();
    }

    /** @inheritDoc */
    public function signInWithPassword(string $email, string $password): array
    {
        $url = $this->baseUrl() . '/auth/v1/token?grant_type=password';

        try {
            $resp = $this->http()->post($url, [
                'email' => $email,
                'password' => $password,
            ]);
        } catch (ConnectionException) {
            throw new ExternalServiceException('Supabase is unreachable (connection error).');
        }

        if (!$resp->successful()) {
            if (in_array($resp->status(), [400, 401], true)) throw new UnauthorizedException('Invalid credentials.');

            throw new ExternalServiceException('Unable to authenticate with Supabase. HTTP ' . $resp->status());
        }

        return (array)$resp->json();
    }

    /** @inheritDoc */
    public function refreshToken(string $refreshToken): array
    {
        $url = $this->baseUrl() . '/auth/v1/token?grant_type=refresh_token';

        try {
            $resp = $this->http()->post($url, [
                'refresh_token' => $refreshToken,
            ]);
        } catch (ConnectionException) {
            throw new ExternalServiceException('Supabase is unreachable (connection error).');
        }

        if (!$resp->successful()) {
            if (in_array($resp->status(), [400, 401], true)) throw new UnauthorizedException('Invalid refresh token.');

            throw new ExternalServiceException('Unable to refresh token with Supabase. HTTP ' . $resp->status());
        }

        return (array)$resp->json();
    }
}
