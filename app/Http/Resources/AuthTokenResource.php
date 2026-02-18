<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource wrapper for Supabase authentication responses.
 *
 * Exposes only a minimal, controlled token payload and basic user identity fields.
 *
 * @property array<string,mixed> $resource
 */
class AuthTokenResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Expected input structure (from AuthService):
     * - access_token
     * - token_type
     * - expires_in
     * - refresh_token
     * - user => [id, email, ...]
     *
     * @param  Request  $request
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'access_token'  => $this->resource['access_token']  ?? null,
            'token_type'    => $this->resource['token_type']    ?? null,
            'expires_in'    => $this->resource['expires_in']    ?? null,
            'refresh_token' => $this->resource['refresh_token'] ?? null,

            // Expose only minimal user fields
            'user' => isset($this->resource['user']) && is_array($this->resource['user'])
                ? [
                    'id'    => $this->resource['user']['id'] ?? null,
                    'email' => $this->resource['user']['email'] ?? null,
                ]
                : null,
        ];
    }
}
