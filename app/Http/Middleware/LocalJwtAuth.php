<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Security\JwtIssuerInterface;
use Closure;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Authenticate API requests against locally issued JWT access tokens.
 */
final readonly class LocalJwtAuth
{
    private const AUTH_CACHE_PREFIX = 'local:auth:'; // local:auth:{sub}

    /**
     * Create a new local JWT middleware instance.
     */
    public function __construct(private JwtIssuerInterface $jwt)
    {
    }

    /**
     * Authenticate request using local JWT (HS256), resolve User, and set Laravel auth user.
     *
     * Caching:
     * - Auth cache maps "sub" => ['token_fp' => ..., 'user_id' => ...] with TTL aligned to token exp (clamped)
     *
     * @param Request $request Incoming HTTP request.
     * @param Closure $next    Next middleware in the pipeline.
     *
     * @throws Throwable Propagates unexpected token parsing or infrastructure failures.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie('access_token') ?: $request->bearerToken();
        if (! $token) {
            return response()->json(['error' => __('api.errors.missing_bearer_token')], 401);
        }

        $tokenFp = $this->tokenFingerprint($token);
        $parts = explode('.', $token);

        try {
            if (count($parts) !== 3) {
                return response()->json(['error' => __('api.errors.invalid_jwt_format')], 401);
            }

            // Verify signature + iss/aud + sub
            $claims = $this->jwt->verify($token);

            $sub = (string) $claims->sub;     // user id in your design
            $userId = (int) $sub;

            if ($userId <= 0) {
                return response()->json(['error' => __('api.errors.invalid_token_payload')], 401);
            }

            $authCacheKey = self::AUTH_CACHE_PREFIX . $sub;
            $ttlSeconds = $this->ttlFromClaims($claims);

            $user = null;

            $cached = Cache::get($authCacheKey);
            if (is_array($cached) && isset($cached['token_fp'], $cached['user_id'])) {
                $cachedFp = (string) $cached['token_fp'];
                $cachedUserId = (int) $cached['user_id'];

                if (! hash_equals($cachedFp, $tokenFp)) {
                    Cache::forget($authCacheKey);
                } else {
                    $user = User::query()->with(['person', 'role'])->find($cachedUserId);

                    if (! $user instanceof User) {
                        Cache::forget($authCacheKey);
                        $user = null;
                    }
                }
            }

            if (! $user instanceof User) {
                $user = User::query()->with(['person', 'role'])->find($userId);

                if (! $user instanceof User) {
                    return response()->json(['error' => __('api.errors.unauthorized')], 401);
                }

                Cache::put($authCacheKey, [
                    'token_fp' => $tokenFp,
                    'user_id' => $user->id,
                ], $ttlSeconds);
            }

            if (! $user->is_active) {
                return response()->json(['error' => __('api.errors.account_inactive')], 403);
            }

            auth()->guard()->setUser($user);

            // Convenience: many controllers/services want the profile quickly
            $request->attributes->set('user', $user);
            $request->attributes->set('person', $user->person);

            return $next($request);
        } catch (ExpiredException) {
            // Token expired -> best effort invalidate cache key using unverified payload
            $this->bestEffortInvalidateCacheFromTokenParts($parts);

            return response()->json(['error' => __('api.errors.token_expired')], 401);
        } catch (Throwable $e) {
            return response()->json([
                'error' => __('api.errors.unauthorized'),
                'details' => $e->getMessage(),
            ], 401);
        }
    }

    /**
     * Create a stable fingerprint for a JWT string.
     */
    private function tokenFingerprint(string $jwt): string
    {
        return hash('sha256', $jwt);
    }

    /**
     * Align cache TTL with exp claim if present (clamped).
     *
     * @param object $claims Verified JWT claims object.
     */
    private function ttlFromClaims(object $claims): int
    {
        $default = 3600;

        $exp = $claims->exp ?? null;
        if (!is_int($exp) && !is_float($exp) && !is_string($exp)) {
            return $default;
        }

        $expInt = (int) $exp;
        $delta = $expInt - time();

        if ($delta <= 0) {
            return 60;
        }

        return max(60, min(86400, $delta));
    }

    /**
     * Decode payload WITHOUT verifying signature (best-effort only) to get sub and clear cache.
     *
     * @param array<int, string> $parts JWT segments produced by exploding the token.
     */
    private function bestEffortInvalidateCacheFromTokenParts(array $parts): void
    {
        try {
            if (count($parts) !== 3) {
                return;
            }

            $payloadJson = JWT::urlsafeB64Decode($parts[1]);
            $payload = JWT::jsonDecode($payloadJson);

            $sub = $payload->sub ?? null;
            if (is_string($sub) && $sub !== '') {
                Cache::forget(self::AUTH_CACHE_PREFIX . $sub);
            }
        } catch (Throwable) {
            // ignore
        }
    }
}
