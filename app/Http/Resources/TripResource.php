<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON representation of a Trip aggregate.
 *
 * Optional relations (when eager-loaded):
 * - driver
 * - departureAddress.city
 * - arrivalAddress.city
 *
 * @property int         $id
 * @property mixed       $departure_time
 * @property mixed       $arrival_time
 * @property float|int   $distance_km
 * @property int         $available_seats
 * @property bool        $smoking_allowed
 */
class TripResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'departure_time'  => $this->departure_time?->toIso8601String(),
            'arrival_time' => $this->arrival_time?->toIso8601String(),
            'distance_km'     => (float) $this->distance_km,
            'available_seats' => $this->available_seats,
            'smoking_allowed' => $this->smoking_allowed,

            'driver' => $this->whenLoaded('driver', function () {
                return $this->driver
                    ? [
                        'id'         => (int) $this->driver->id,
                        'first_name' => $this->driver->first_name !== null ? (string) $this->driver->first_name : null,
                        'last_name'  => $this->driver->last_name !== null ? (string) $this->driver->last_name : null,
                        'pseudo'     => (string) $this->driver->pseudo,
                        'car'        => $this->driver->relationLoaded('car') && $this->driver->car
                            ? [
                                'id'            => (int) $this->driver->car->id,
                                'license_plate' => (string) $this->driver->car->license_plate,
                                'model'         => $this->driver->car->relationLoaded('model') && $this->driver->car->model
                                    ? [
                                        'id'    => (int) $this->driver->car->model->id,
                                        'name'  => (string) $this->driver->car->model->name,
                                        'brand' => $this->driver->car->model->relationLoaded('brand') && $this->driver->car->model->brand
                                            ? [
                                                'id'   => (int) $this->driver->car->model->brand->id,
                                                'name' => (string) $this->driver->car->model->brand->name,
                                            ]
                                            : null,
                                    ]
                                    : null,
                                'color'         => $this->driver->car->relationLoaded('color') && $this->driver->car->color
                                    ? [
                                        'name'     => (string) $this->driver->car->color->name,
                                        'hex_code' => (string) $this->driver->car->color->hex_code,
                                    ]
                                    : null,
                            ]
                            : null,
                    ]
                    : null;
            }),

            'departure_address' => $this->whenLoaded('departureAddress', function () {
                return $this->departureAddress
                    ? [
                        'id'            => (int) $this->departureAddress->id,
                        'street'        => (string) $this->departureAddress->street,
                        'street_number' => (string) $this->departureAddress->street_number,
                        'city'          => $this->departureAddress->relationLoaded('city') && $this->departureAddress->city
                            ? [
                                'id'          => (int) $this->departureAddress->city->id,
                                'name'        => (string) $this->departureAddress->city->name,
                                'postal_code' => (string) $this->departureAddress->city->postal_code,
                            ]
                            : null,
                    ]
                    : null;
            }),

            'arrival_address' => $this->whenLoaded('arrivalAddress', function () {
                return $this->arrivalAddress
                    ? [
                        'id'            => (int) $this->arrivalAddress->id,
                        'street'        => (string) $this->arrivalAddress->street,
                        'street_number' => (string) $this->arrivalAddress->street_number,
                        'city'          => $this->arrivalAddress->relationLoaded('city') && $this->arrivalAddress->city
                            ? [
                                'id'          => (int) $this->arrivalAddress->city->id,
                                'name'        => (string) $this->arrivalAddress->city->name,
                                'postal_code' => (string) $this->arrivalAddress->city->postal_code,
                            ]
                            : null,
                    ]
                    : null;
            }),
        ];
    }
}
