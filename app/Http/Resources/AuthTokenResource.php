<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource wrapper for local JWT authentication responses.
 *
 * @property array<string,mixed> $resource
 */
class AuthTokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'access_token'  => $this->resource['access_token']  ?? null,
            'token_type'    => $this->resource['token_type']    ?? 'Bearer',
            'expires_in'    => $this->resource['expires_in']    ?? null,
            'refresh_token' => $this->resource['refresh_token'] ?? null,
            'person_id'     => $this->resource['person_id']    ?? null,
            'role_id'       => $this->resource['role_id']       ?? null,
        ];
    }
}
