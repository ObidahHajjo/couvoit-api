<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON representation of a Person aggregate.
 *
 * Optional relations (when eager-loaded):
 * - role
 * - car
 * - car.model
 * - car.model.brand
 * - car.color
 *
 * @property int         $id
 * @property string|null $email
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string      $pseudo
 * @property string|null $phone
 * @property bool        $is_active
 */
class PersonResource extends JsonResource
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
            'id'         => $this->id,
            'email'      => $this->email !== null ? (string) $this->email : auth()->user()->email,
            'first_name' => $this->first_name !== null ? (string) $this->first_name : null,
            'last_name'  => $this->last_name !== null ? (string) $this->last_name : null,
            'pseudo'     => $this->pseudo,
            'phone'      => $this->phone !== null ? (string) $this->phone : null,
            'is_active'  => $this->is_active,

            'role' => $this->whenLoaded('role', function () {
                return $this->role
                    ? [
                        'id'   => (int) $this->role->id,
                        'name' => (string) $this->role->name,
                    ]
                    : null;
            }),

            'car' => $this->whenLoaded('car', function () {
                if ($this->car === null) {
                    return null;
                }

                return [
                    'id'            => (int) $this->car->id,
                    'license_plate' => (string) $this->car->license_plate,

                    'model' => $this->car->relationLoaded('model') && $this->car->model !== null
                        ? [
                            'id'    => (int) $this->car->model->id,
                            'name'  => (string) $this->car->model->name,
                            'seats' => $this->car->model->seats !== null ? (int) $this->car->model->seats : null,

                            'brand' => $this->car->model->relationLoaded('brand') && $this->car->model->brand !== null
                                ? [
                                    'id'   => (int) $this->car->model->brand->id,
                                    'name' => (string) $this->car->model->brand->name,
                                ]
                                : null,
                        ]
                        : null,

                    'color' => $this->car->relationLoaded('color') && $this->car->color !== null
                        ? [
                            'id'       => (int) $this->car->color->id,
                            'name'     => (string) $this->car->color->name,
                            'hex_code' => (string) $this->car->color->hex_code,
                        ]
                        : null,
                ];
            }),
        ];
    }
}
