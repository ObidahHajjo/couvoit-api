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
    public function handle(Request $request, Closure $next): Response
    {
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

            $jwks = $this->getJwks();
            $keySet = JWK::parseKeySet($jwks);

            // firebase/php-jwt v6.10: third param is headers passed by reference
            $decodedHeaders = new \stdClass();
            $payload = JWT::decode($token, $keySet, $decodedHeaders);

            // Extra safety: ensure decoded header alg matches what we allow
            if (!isset($decodedHeaders->alg) || $decodedHeaders->alg !== $alg) {
                return response()->json(['error' => 'JWT header mismatch'], 401);
            }

            $supabaseUserId = $payload->sub ?? null;
            $email = $payload->email ?? null;

            if (!$supabaseUserId) {
                return response()->json(['error' => 'Invalid token payload (no sub)'], 401);
            }

            $person = Person::query()
                ->where('supabase_user_id', $supabaseUserId)
                ->first();

            if (!$person) {
                return response()->json([
                    'error' => 'Profile not found',
                    'details' => 'No person row linked to this Supabase user',
                ], 403);
            }

            auth()->setUser($person);
            $request->attributes->set('person', $person);

            return $next($request);

        } catch (\Firebase\JWT\ExpiredException $e) {
            return response()->json(['error' => 'Token expired'], 401);

        } catch (\Throwable $e) {
            // Key rotation often produces "kid" / "Key ID" related errors -> refetch JWKS next time
            if (str_contains($e->getMessage(), 'kid') || str_contains($e->getMessage(), 'Key ID')) {
                Cache::forget('supabase_jwks');
            }

            return response()->json([
                'error' => 'Unauthorized',
                'details' => $e->getMessage(),
            ], 401);
        }
    }

    private function getJwks(): array
    {
        return Cache::remember('supabase_jwks', 60 * 60 * 6, function () {
            $jwksUrl = config('services.supabase.jwks_url');
            if (!$jwksUrl) {
                throw new \RuntimeException('SUPABASE_JWT_JWKS_URL is not set');
            }

            $resp = Http::withHeaders([
                'apikey' => config('services.supabase.anon_key'),
            ])->get($jwksUrl);

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
