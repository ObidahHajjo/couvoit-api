<?php

namespace App\Security;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;

/**
 * Firebase JWT-backed access token issuer and verifier.
 */
final class JwtIssuer implements JwtIssuerInterface
{
    private const ALG = 'HS256';

    /**
     * Resolve the configured JWT signing secret.
     */
    private function secret(): string
    {
        $secret = (string) config('jwt.secret');

        if ($secret === '') {
            throw new RuntimeException('JWT_SECRET is not set');
        }

        // Support "base64:..." syntax
        if (str_starts_with($secret, 'base64:')) {
            $decoded = base64_decode(substr($secret, 7), true);
            if ($decoded === false) {
                throw new RuntimeException('JWT_SECRET base64 decoding failed');
            }
            return $decoded;
        }

        return $secret;
    }

    /** {@inheritDoc} */
    public function issueAccessToken(User $user): string
    {
        $now = time();
        $ttl = (int) config('jwt.access_ttl', 900);

        $payload = [
            'iss' => (string) config('jwt.issuer', 'couvoit-api'),
            'aud' => (string) config('jwt.audience', 'couvoit-client'),
            'iat' => $now,
            'exp' => $now + max(60, $ttl),

            // Auth subject = USER id
            'sub' => (string) $user->id,

            // Helpful claims
            'email' => $user->email,
            'role_id' => $user->role_id,
            'jti' => bin2hex(random_bytes(16)),
        ];

        return JWT::encode($payload, $this->secret(), self::ALG);
    }

    /** {@inheritDoc} */
    public function verify(string $jwt): object
    {
        $decoded = JWT::decode($jwt, new Key($this->secret(), self::ALG));

        $iss = (string) config('jwt.issuer', 'couvoit-api');
        $aud = (string) config('jwt.audience', 'couvoit-client');

        if (($decoded->iss ?? null) !== $iss) {
            throw new RuntimeException('Invalid issuer');
        }

        if (($decoded->aud ?? null) !== $aud) {
            throw new RuntimeException('Invalid audience');
        }

        $sub = $decoded->sub ?? null;
        if (!is_string($sub) || $sub === '') {
            throw new RuntimeException('Missing sub');
        }

        return $decoded;
    }
}
