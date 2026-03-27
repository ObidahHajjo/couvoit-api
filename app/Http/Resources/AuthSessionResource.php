<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource wrapper for cookie-based auth session responses.
 *
 * @property array<string,mixed> $resource
 */
class AuthSessionResource extends JsonResource
{
    /**
     * Transform the session payload into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'message' => $this->resource['message'] ?? null,
        ];
    }
}
