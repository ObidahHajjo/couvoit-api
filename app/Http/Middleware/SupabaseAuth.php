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
    // JWKS
    private const JWKS_CACHE_KEY = 'supabase:jwks';
    private const JWKS_TTL_SECONDS = 86400; // 24h
    private const JWKS_KEY_CACHE_PREFIX = 'supabase:jwks:key:'; // supabase:jwks:key:{kid}
    private const JWKS_KEY_TTL_SECONDS = 86400; // 24h

    // Auth “session” cache (sub -> {token_fp, person_id})
    private const AUTH_CACHE_PREFIX = 'supabase:auth:'; // supabase:auth:{sub}

    public function __construct(private PersonRepositoryInterface $repo)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['error' => 'Missing Bearer token'], 401);
        }

        // Compute fingerprint early (used for cache validation)
        $tokenFp = $this->tokenFingerprint($token);

        // We'll need header parts in catch() too
        $parts = explode('.', $token);

        try {
            if (count($parts) !== 3) {
                return response()->json(['error' => 'Invalid JWT format'], 401);
            }

            $tokenHeader = JWT::jsonDecode(JWT::urlsafeB64Decode($parts[0]));
            $alg = $tokenHeader->alg ?? null;
            $kid = $tokenHeader->kid ?? null;

            if (!is_string($alg) || !is_string($kid) || $alg === '' || $kid === '') {
                return response()->json(['error' => 'Invalid token header (missing alg or kid)'], 401);
            }

            $allowedAlgs = ['ES256'];
            if (!in_array($alg, $allowedAlgs, true)) {
                return response()->json(['error' => 'Unsupported JWT alg'], 401);
            }

            $jwks = $this->getJwks();
            $key = $this->getCachedKeyForKid($jwks, $kid);

            $decodedHeaders = new stdClass();
            $payload = JWT::decode($token, $key, $decodedHeaders);

            if (!isset($decodedHeaders->alg) || $decodedHeaders->alg !== $alg) {
                return response()->json(['error' => 'JWT header mismatch'], 401);
            }

            $supabaseUserId = $payload->sub ?? null;
            if (!is_string($supabaseUserId) || $supabaseUserId === '') {
                return response()->json(['error' => 'Invalid token payload (no sub)'], 401);
            }

            $authCacheKey = self::AUTH_CACHE_PREFIX . $supabaseUserId;

            // TTL: try to align with token exp (if present)
            $ttlSeconds = $this->ttlFromPayload($payload);

            /**
             * Cache contract:
             *   supabase:auth:{sub} => ['token_fp' => '...', 'person_id' => 123]
             *
             * If request token_fp != cached token_fp:
             *   -> invalidate cached auth entry
             *   -> re-resolve person by sub
             *   -> insert new token_fp + person_id
             */
            $cachedAuth = Cache::get($authCacheKey);

            $person = null;

            if (is_array($cachedAuth) && isset($cachedAuth['token_fp'], $cachedAuth['person_id'])) {
                $cachedFp = (string) $cachedAuth['token_fp'];
                $cachedPersonId = (int) $cachedAuth['person_id'];

                // If token differs => invalidate and refresh
                if (!hash_equals($cachedFp, $tokenFp)) {
                    Cache::forget($authCacheKey);
                } else {
                    // Token matches cache: load person (repo may cache by id)
                    try {
                        $person = $this->repo->findById($cachedPersonId);
                    } catch (Throwable) {
                        // If cached person_id is stale, force refresh
                        $person = null;
                        Cache::forget($authCacheKey);
                    }

                    // Safety: ensure person matches the same sub
                    if ($person instanceof Person && (string) $person->supabase_user_id !== (string) $supabaseUserId) {
                        $person = null;
                        Cache::forget($authCacheKey);
                    }
                }
            }

            // If no valid cached person, resolve by sub and write cache
            if (!$person instanceof Person) {
                $person = $this->repo->findBySupabaseUserId($supabaseUserId);

                if (!$person instanceof Person) {
                    return response()->json(['error' => 'Unauthorized'], 401);
                }

                // Must match sub (explicit)
                if ((string) $person->supabase_user_id !== (string) $supabaseUserId) {
                    return response()->json(['error' => 'Unauthorized'], 401);
                }

                Cache::put($authCacheKey, [
                    'token_fp'  => $tokenFp,
                    'person_id' => (int) $person->id,
                ], $ttlSeconds);
            }

            if (!$person->is_active) {
                return response()->json(['error' => 'Account inactive'], 403);
            }

            auth()->setUser($person);
            $request->attributes->set('person', $person);
            return $next($request);

        } catch (ExpiredException) {
            // Token expired: invalidate auth cache for this sub if we can decode sub without verifying signature
            $this->bestEffortInvalidateAuthCacheFromTokenParts($parts);
            return response()->json(['error' => 'Token expired'], 401);

        } catch (Throwable $e) {
            $msg = $e->getMessage();

            // key rotation / kid problems: purge caches so next request refetches/reparses
            if (str_contains($msg, 'kid') || str_contains($msg, 'Key ID')) {
                Cache::forget(self::JWKS_CACHE_KEY);

                try {
                    if (count($parts) === 3) {
                        $tokenHeader = JWT::jsonDecode(JWT::urlsafeB64Decode($parts[0]));
                        $kid = $tokenHeader->kid ?? null;
                        if (is_string($kid) && $kid !== '') {
                            Cache::forget(self::JWKS_KEY_CACHE_PREFIX . $kid);
                        }
                    }
                } catch (Throwable) {
                    // ignore
                }
            }

            return response()->json([
                'error' => 'Unauthorized',
                'details' => $e->getMessage(),
            ], 401);
        }
    }

    private function tokenFingerprint(string $jwt): string
    {
        return hash('sha256', $jwt);
    }

    private function ttlFromPayload(object $payload): int
    {
        // Default if no exp
        $default = 3600; // 1h

        $exp = $payload->exp ?? null;
        if (!is_int($exp) && !is_float($exp) && !is_string($exp)) {
            return $default;
        }

        $expInt = (int) $exp;
        $now = time();
        $delta = $expInt - $now;

        // If token already expired, minimal TTL (doesn't matter much)
        if ($delta <= 0) {
            return 60;
        }

        // Clamp: keep it reasonable for cache (min 60s, max 24h)
        return max(60, min(86400, $delta));
    }

    private function bestEffortInvalidateAuthCacheFromTokenParts(array $parts): void
    {
        try {
            if (count($parts) !== 3) return;

            // Decode payload WITHOUT verification (best effort only) just to get sub
            $payload = JWT::jsonDecode(JWT::urlsafeB64Decode($parts[1]));
            $sub = $payload->sub ?? null;

            if (is_string($sub) && $sub !== '') {
                Cache::forget(self::AUTH_CACHE_PREFIX . $sub);
            }
        } catch (Throwable) {
            // ignore
        }
    }

    private function getJwks(): array
    {
        return Cache::remember(self::JWKS_CACHE_KEY, self::JWKS_TTL_SECONDS, function () {
            $jwksUrl = config('services.supabase.jwks_url');
            if (!$jwksUrl) throw new RuntimeException('SUPABASE_JWT_JWKS_URL is not set');

            $resp = Http::timeout(3)
                ->retry(2, 200)
                ->get($jwksUrl);

            if (!$resp->successful()) {
                throw new RuntimeException(
                    'Unable to fetch Supabase JWKS: HTTP ' . $resp->status() . ' ' . $resp->body()
                );
            }

            $json = $resp->json();
            if (!is_array($json)) throw new RuntimeException('Supabase JWKS response is not valid JSON');

            return $json;
        });
    }

    private function getCachedKeyForKid(array $jwks, string $kid): Key
    {
        $cacheKey = self::JWKS_KEY_CACHE_PREFIX . $kid;

        $cached = Cache::get($cacheKey);
        if ($cached instanceof Key) {
            return $cached;
        }

        $key = $this->parseKeyForKid($jwks, $kid);

        Cache::put($cacheKey, $key, self::JWKS_KEY_TTL_SECONDS);

        return $key;
    }

    private function parseKeyForKid(array $jwks, string $kid): Key
    {
        $keys = $jwks['keys'] ?? null;
        if (!is_array($keys)) throw new RuntimeException('JWKS malformed: missing keys[]');

        foreach ($keys as $jwk) {
            if (!is_array($jwk)) continue;

            if (($jwk['kid'] ?? null) === $kid) {
                $parsed = JWK::parseKey($jwk);

                if (!$parsed instanceof Key) throw new RuntimeException('Failed to parse JWK for kid=' . $kid);

                return $parsed;
            }
        }

        throw new RuntimeException('kid not found in JWKS: ' . $kid);
    }
}
