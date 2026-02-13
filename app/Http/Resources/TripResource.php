<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripResource extends JsonResource
{
    /**
     * @return array<string,mixed>
     */
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'departure_time'  => $this->departure_time,
            'distance_km'     => (float) $this->distance_km,
            'available_seats' => (int) $this->available_seats,
            'smoking_allowed' => (bool) $this->smoking_allowed,

            'driver' => $this->whenLoaded('driver', fn () => [
                'id'         => $this->driver->id,
                'first_name' => $this->driver->first_name,
                'last_name'  => $this->driver->last_name,
                'pseudo'     => $this->driver->pseudo,
            ]),

            'departure_address' => $this->whenLoaded('departureAddress', fn () => [
                'id'            => $this->departureAddress->id,
                'street'        => $this->departureAddress->street,
                'street_number' => $this->departureAddress->street_number,
                'city'          => $this->departureAddress->relationLoaded('city') && $this->departureAddress->city
                    ? ['id' => $this->departureAddress->city->id, 'name' => $this->departureAddress->city->name, 'postal_code' => $this->departureAddress->city->postal_code]
                    : null,
            ]),

            'arrival_address' => $this->whenLoaded('arrivalAddress', fn () => [
                'id'            => $this->arrivalAddress->id,
                'street'        => $this->arrivalAddress->street,
                'street_number' => $this->arrivalAddress->street_number,
                'city'          => $this->arrivalAddress->relationLoaded('city') && $this->arrivalAddress->city
                    ? ['id' => $this->arrivalAddress->city->id, 'name' => $this->arrivalAddress->city->name, 'postal_code' => $this->arrivalAddress->city->postal_code]
                    : null,
            ]),
        ];
    }
}
