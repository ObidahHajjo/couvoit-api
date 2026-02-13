<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonResource extends JsonResource
{
    /**
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'email'      => $this->email,
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name,
            'pseudo'     => $this->pseudo,
            'phone'      => $this->phone,
            'is_active'  => (bool) $this->is_active,

            'role' => $this->whenLoaded('role', fn () => [
                'id'   => $this->role->id,
                'name' => $this->role->name,
            ]),

            'car' => $this->whenLoaded('car', fn () => [
                'id'            => $this->car->id,
                'license_plate' => $this->car->license_plate,
            ]),
        ];
    }
}
