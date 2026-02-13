<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'license_plate' => $this->license_plate,

            'model' => $this->whenLoaded('model', function () {
                return [
                    'id'    => $this->model->id,
                    'name'  => $this->model->name,
                    'seats' => $this->model->seats,

                    'brand' => $this->model->whenLoaded('brand', function () {
                        return [
                            'id'   => $this->model->brand->id,
                            'name' => $this->model->brand->name,
                        ];
                    }),

                    'type' => $this->model->whenLoaded('type', function () {
                        return [
                            'id'   => $this->model->type->id,
                            'type' => $this->model->type->type,
                        ];
                    }),
                ];
            }),

            'color' => $this->whenLoaded('color', function () {
                return [
                    'id'       => $this->color->id,
                    'hex_code' => $this->color->hex_code,
                ];
            }),
        ];
    }
}
