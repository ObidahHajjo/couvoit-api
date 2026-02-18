<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON representation of a Car aggregate.
 *
 * Expected relations (optionally eager-loaded):
 * - model
 * - model.brand
 * - model.type
 * - color
 *
 * @property int         $id
 * @property string      $license_plate
 */
class CarResource extends JsonResource
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
            'id'            => $this->id,
            'license_plate' => $this->license_plate,

            'model' => $this->whenLoaded('model', function () {
                return [
                    'id'    => (int) $this->model->id,
                    'name'  => (string) $this->model->name,
                    'seats' => (int) $this->model->seats,

                    'brand' => $this->when(
                        $this->model->relationLoaded('brand') && $this->model->brand,
                        function () {
                            return [
                                'id'   => (int) $this->model->brand->id,
                                'name' => (string) $this->model->brand->name,
                            ];
                        }
                    ),

                    'type' => $this->when(
                        $this->model->relationLoaded('type') && $this->model->type,
                        function () {
                            return [
                                'id'   => (int) $this->model->type->id,
                                'type' => (string) $this->model->type->type,
                            ];
                        }
                    ),
                ];
            }),

            'color' => $this->whenLoaded('color', function () {
                return [
                    'id'       => (int) $this->color->id,
                    'hex_code' => (string) $this->color->hex_code,
                ];
            }),
        ];
    }
}
