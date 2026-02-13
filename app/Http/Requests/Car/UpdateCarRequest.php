<?php

namespace App\Http\Requests\Car;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Only normalize what is actually present in the incoming payload.

        // Normalize carregistration / license_plate if present (optional)
        $plate = $this->input('carregistration');
        if ($plate === null) {
            $plate = $this->input('license_plate'); // accept alternative key
            if ($plate !== null) {
                $this->merge(['carregistration' => $plate]);
            }
        }
        if ($plate !== null) {
            $raw = (string) $plate;
            $normalized = strtoupper(preg_replace('/[\s\-]+/', '', $raw) ?? '');
            $this->merge(['carregistration' => $normalized]);
        }

        // Normalize nested names only if those objects exist
        if ($this->has('brand')) {
            $this->merge([
                'brand' => array_merge((array) $this->input('brand', []), [
                    'name' => strtolower((string) data_get($this->input('brand', []), 'name', '')),
                ]),
            ]);
        }

        if ($this->has('type')) {
            $this->merge([
                'type' => array_merge((array) $this->input('type', []), [
                    'name' => strtolower((string) data_get($this->input('type', []), 'name', '')),
                ]),
            ]);
        }

        if ($this->has('model')) {
            $this->merge([
                'model' => array_merge((array) $this->input('model', []), [
                    'name' => strtolower((string) data_get($this->input('model', []), 'name', '')),
                ]),
            ]);
        }

        if ($this->has('color')) {
            $hex = strtoupper((string) data_get($this->input('color', []), 'hex_code', ''));
            $this->merge([
                'color' => array_merge((array) $this->input('color', []), [
                    'name' => strtolower((string) data_get($this->input('color', []), 'name', '')),
                    'hex_code' => $hex,
                ]),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            // At least one updatable field must be present (optional but recommended)
            // 'carregistration' or 'model' or 'color'
            'carregistration' => ['sometimes', 'string', 'regex:/^[A-Z0-9]{2,12}$/'],

            'color' => ['sometimes', 'array'],
            'color.name' => ['required_with:color', 'string', 'min:1', 'max:50'],
            'color.hex_code' => ['required_with:color', 'string', 'size:7', 'regex:/^#[0-9A-F]{6}$/'],

            'model' => ['sometimes', 'array'],
            'model.name' => ['required_with:model', 'string', 'min:1', 'max:255'],
            'model.seats' => ['required_with:model', 'integer', 'min:1', 'max:9'],

            // Only required when updating model, because your service needs brand/type to create/find a model
            'brand' => ['required_with:model', 'array'],
            'brand.name' => ['required_with:model', 'string', 'min:1', 'max:50'],

            'type' => ['required_with:model', 'array'],
            'type.name' => ['required_with:model', 'string', 'min:1', 'max:50'],
        ];
    }
}
