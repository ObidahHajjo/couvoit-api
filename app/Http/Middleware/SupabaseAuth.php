<?php

namespace App\Http\Middleware;

use App\Models\Person;
use Closure;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class SupabaseAuth
{
    private const JWKS_CACHE_KEY = 'supabase:jwks';
    private const JWKS_TTL_SECONDS = 86400; // 24h

    private const PERSON_CACHE_PREFIX = 'persons:supabase:'; // persons:supabase:{uuid}
    private const PERSON_TTL_SECONDS = 300; // 5 min

    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['error' => 'Missing Bearer token'], 401);
        }

        try {
            // Basic JWT format validation
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return response()->json(['error' => 'Invalid JWT format'], 401);
            }

            // Read header (alg/kid)
            $tokenHeader = JWT::jsonDecode(JWT::urlsafeB64Decode($parts[0]));
            $alg = $tokenHeader->alg ?? null;
            $kid = $tokenHeader->kid ?? null;

            if (!$alg || !$kid) {
                return response()->json(['error' => 'Invalid token header (missing alg or kid)'], 401);
            }

            // Enforce supported algorithms (Supabase commonly ES256)
            $allowedAlgs = ['ES256'];
            if (!in_array($alg, $allowedAlgs, true)) {
                return response()->json(['error' => 'Unsupported JWT alg'], 401);
            }

            // Fetch JWKS from cache/HTTP
            $jwks = $this->getJwks();

            // Parse keys; decode token
            // (Parsing keys is cheap compared to HTTP; caching JWKS is the real win)
            $keySet = JWK::parseKeySet($jwks);

            $decodedHeaders = new \stdClass();
            $payload = JWT::decode($token, $keySet, $decodedHeaders);

            // Extra safety: ensure decoded header alg matches what we allow
            if (!isset($decodedHeaders->alg) || $decodedHeaders->alg !== $alg) {
                return response()->json(['error' => 'JWT header mismatch'], 401);
            }

            $supabaseUserId = $payload->sub ?? null;
            if (!is_string($supabaseUserId) || $supabaseUserId === '') {
                return response()->json(['error' => 'Invalid token payload (no sub)'], 401);
            }

            // Cache person lookup (includes relations to avoid policy N+1)
            $cacheKey = self::PERSON_CACHE_PREFIX . $supabaseUserId;

            /** @var Person|null $person */
            $person = Cache::remember($cacheKey, self::PERSON_TTL_SECONDS, function () use ($supabaseUserId) {
                return Person::query()
                    ->with(['role', 'car'])
                    ->where('supabase_user_id', $supabaseUserId)
                    ->first();
            });

            if (!$person) {
                return response()->json([
                    'error'   => 'Profile not found',
                    'details' => 'No person row linked to this Supabase user',
                ], 403);
            }

            // Optional: block inactive users globally
            if (!$person->is_active) {
                return response()->json(['error' => 'Account inactive'], 403);
            }

            auth()->setUser($person);
            $request->attributes->set('person', $person);
            \Log::info('SupabaseAuth seconds', ['t' => microtime(true) - $start]);
            return $next($request);

        } catch (\Firebase\JWT\ExpiredException $e) {
            return response()->json(['error' => 'Token expired'], 401);

        } catch (\Throwable $e) {
            // If key rotation / kid problems, purge JWKS cache so next request refetches
            $msg = $e->getMessage();
            if (is_string($msg) && (str_contains($msg, 'kid') || str_contains($msg, 'Key ID'))) {
                Cache::forget(self::JWKS_CACHE_KEY);
            }

            return response()->json([
                'error'   => 'Unauthorized',
                'details' => $e->getMessage(),
            ], 401);
        }
    }

    private function getJwks(): array
    {
        return Cache::remember(self::JWKS_CACHE_KEY, self::JWKS_TTL_SECONDS, function () {
            $jwksUrl = config('services.supabase.jwks_url');
            if (!$jwksUrl) {
                throw new \RuntimeException('SUPABASE_JWT_JWKS_URL is not set');
            }

            // JWKS endpoint is public; apikey header is not required for fetching JWKS
            $resp = Http::timeout(3)
                ->retry(2, 200)
                ->get($jwksUrl);

            if (!$resp->successful()) {
                throw new \RuntimeException(
                    'Unable to fetch Supabase JWKS: HTTP ' . $resp->status() . ' ' . $resp->body()
                );
            }

            $json = $resp->json();
            if (!is_array($json)) {
                throw new \RuntimeException('Supabase JWKS response is not valid JSON');
            }

            return $json;
        });
    }
}
