<?php

namespace App\Http\Middleware;

use App\Models\Person;
use App\Repositories\Interfaces\PersonRepositoryInterface;
use Closure;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Log;
use RuntimeException;
use stdClass;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SupabaseAuth
{
    private const JWKS_CACHE_KEY = 'supabase:jwks';
    private const JWKS_TTL_SECONDS = 86400; // 24h

    // Cache parsed Key per kid (fast path)
    private const JWKS_KEY_CACHE_PREFIX = 'supabase:jwks:key:'; // supabase:jwks:key:{kid}
    private const JWKS_KEY_TTL_SECONDS = 86400; // 24h

    private const PERSON_CACHE_PREFIX = 'persons:supabase:'; // persons:supabase:{uuid}
    private const PERSON_TTL_SECONDS = 3600; // 1h (increase; invalidate on profile updates)

    public function __construct(private PersonRepositoryInterface $repo)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        Log::info('SupabaseAuth middleware HIT', [
            'path' => $request->path(),
            'has_bearer' => (bool) $request->bearerToken(),
        ]);

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

            if (!is_string($alg) || !is_string($kid) || $alg === '' || $kid === '') {
                return response()->json(['error' => 'Invalid token header (missing alg or kid)'], 401);
            }

            // Enforce supported algorithms (Supabase commonly ES256)
            $allowedAlgs = ['ES256'];
            if (!in_array($alg, $allowedAlgs, true)) {
                return response()->json(['error' => 'Unsupported JWT alg'], 401);
            }

            // Fetch JWKS (cached)
            $jwks = $this->getJwks();

            // Parse ONLY the matching key for this kid (cached)
            $key = $this->getCachedKeyForKid($jwks, $kid);

            $decodedHeaders = new stdClass();
            $payload = JWT::decode($token, $key, $decodedHeaders);

            // Extra safety: ensure decoded header alg matches what we allow
            if (!isset($decodedHeaders->alg) || $decodedHeaders->alg !== $alg) {
                return response()->json(['error' => 'JWT header mismatch'], 401);
            }

            $supabaseUserId = $payload->sub ?? null;
            if (!is_string($supabaseUserId) || $supabaseUserId === '') {
                return response()->json(['error' => 'Invalid token payload (no sub)'], 401);
            }

            // Cache person lookup
            $cacheKey = self::PERSON_CACHE_PREFIX . $supabaseUserId;

            /** @var Person $person */
//            $person = Cache::remember($cacheKey, self::PERSON_TTL_SECONDS, function () use ($supabaseUserId) {
//                return Person::query()
//                    ->with(['car']) // FIX: include is_active
//                    // ->with(['role']) // enable if your policies/resources access role to avoid lazy-load queries
//                    ->where('supabase_user_id', $supabaseUserId)
//                    ->firstOrFail();
//            });
            $person = $this->repo->findBySupabaseUserId($supabaseUserId);

            // Optional: block inactive users globally
            if (!$person->is_active) {
                return response()->json(['error' => 'Account inactive'], 403);
            }

            auth()->setUser($person);
            $request->attributes->set('person', $person);

            Log::info('SupabaseAuth seconds', ['t' => microtime(true) - $start]);

            return $next($request);

        } catch (ExpiredException) {
            return response()->json(['error' => 'Token expired'], 401);

        } catch (Throwable $e) {
            // If key rotation / kid problems, purge JWKS + parsed-key cache so next request refetches/reparses
            $msg = $e->getMessage();
            if ((str_contains($msg, 'kid') || str_contains($msg, 'Key ID'))) {
                Cache::forget(self::JWKS_CACHE_KEY);

                // best-effort: also clear parsed key for current kid if we have it
                try {
                    $tokenHeader = JWT::jsonDecode(JWT::urlsafeB64Decode($parts[0]));
                    $kid = $tokenHeader->kid ?? null;
                    if (is_string($kid) && $kid !== '') Cache::forget(self::JWKS_KEY_CACHE_PREFIX . $kid);
                } catch (Throwable) {
                }
            }

            return response()->json([
                'error' => 'Unauthorized',
                'details' => $e->getMessage(),
            ], 401);
        }
    }

    private function getJwks(): array
    {
        return Cache::remember(self::JWKS_CACHE_KEY, self::JWKS_TTL_SECONDS, function () {
            $jwksUrl = config('services.supabase.jwks_url');
            if (!$jwksUrl) {
                throw new RuntimeException('SUPABASE_JWT_JWKS_URL is not set');
            }

            // JWKS endpoint is public; apikey header is not required for fetching JWKS
            $resp = Http::timeout(3)
                ->retry(2, 200)
                ->get($jwksUrl);

            if (!$resp->successful()) {
                throw new RuntimeException(
                    'Unable to fetch Supabase JWKS: HTTP ' . $resp->status() . ' ' . $resp->body()
                );
            }

            $json = $resp->json();
            if (!is_array($json)) {
                throw new RuntimeException('Supabase JWKS response is not valid JSON');
            }

            return $json;
        });
    }

    /**
     * Returns a cached parsed Key for a given kid. If cache contains an unexpected value,
     * it reparses and overwrites.
     */
    private function getCachedKeyForKid(array $jwks, string $kid): Key
    {
        $cacheKey = self::JWKS_KEY_CACHE_PREFIX . $kid;

        $cached = Cache::get($cacheKey);
        if ($cached instanceof Key) {
            return $cached;
        }

        $key = $this->parseKeyForKid($jwks, $kid);

        // Store parsed key (works with Redis/Memcached; if using a file/database cache and it fails,
        // it will simply re-parse next time).
        Cache::put($cacheKey, $key, self::JWKS_KEY_TTL_SECONDS);

        return $key;
    }

    /**
     * Parse ONLY the matching JWK from the JWKS.
     */
    private function parseKeyForKid(array $jwks, string $kid): Key
    {
        $keys = $jwks['keys'] ?? null;
        if (!is_array($keys)) {
            throw new RuntimeException('JWKS malformed: missing keys[]');
        }

        foreach ($keys as $jwk) {
            if (!is_array($jwk)) {
                continue;
            }

            if (($jwk['kid'] ?? null) === $kid) {
                $parsed = JWK::parseKey($jwk);

                if (!$parsed instanceof Key) {
                    throw new RuntimeException('Failed to parse JWK for kid=' . $kid);
                }

                return $parsed;
            }
        }

        throw new RuntimeException('kid not found in JWKS: ' . $kid);
    }
}
