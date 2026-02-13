<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthTokenResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        // $this->resource is the array returned by AuthService
        return [
            'access_token'  => $this->resource['access_token']  ?? null,
            'token_type'    => $this->resource['token_type']    ?? null,
            'expires_in'    => $this->resource['expires_in']    ?? null,
            'refresh_token' => $this->resource['refresh_token'] ?? null,

            // keep only minimal user fields
            'user' => isset($this->resource['user']) && is_array($this->resource['user'])
                ? [
                    'id'    => $this->resource['user']['id'] ?? null,
                    'email' => $this->resource['user']['email'] ?? null,
                ]
                : null,
        ];
    }
}
