<?php

namespace App\Http\Requests\Car;

use Illuminate\Foundation\Http\FormRequest;

class StoreCarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('brand'))) {
            $this->merge([
                'brand' => ['name' => $this->input('brand')],
            ]);
        }

        if (is_string($this->input('model'))) {
            $this->merge([
                'model' => array_merge((array) $this->input('model', []), [
                    'name' => $this->input('model'),
                ]),
            ]);
        }

        if ($this->has('seats')) {
            $this->merge([
                'model' => array_merge((array) $this->input('model', []), [
                    'seats' => $this->input('seats'),
                ]),
            ]);
        }

        $raw = (string) ($this->input('carregistration') ?? '');
        $normalized = strtoupper(preg_replace('/[\s\-]+/', '', $raw) ?? '');

        $this->merge([
            'carregistration' => $normalized,
        ]);

        // --- Normalize nested names and hex casing ---
        $hex = (string) data_get($this->input('color', []), 'hex_code', '');
        $hex = strtoupper($hex);

        $this->merge([
            'brand' => array_merge((array) $this->input('brand', []), [
                'name' => strtolower((string) data_get($this->input('brand', []), 'name', '')),
            ]),
            'type' => array_merge((array) $this->input('type', []), [
                'name' => strtolower((string) data_get($this->input('type', []), 'name', '')),
            ]),
            'model' => array_merge((array) $this->input('model', []), [
                'name' => strtolower((string) data_get($this->input('model', []), 'name', '')),
            ]),
            'color' => array_merge((array) $this->input('color', []), [
                'name' => strtolower((string) data_get($this->input('color', []), 'name', '')),
                'hex_code' => $hex,
            ]),
        ]);
    }

    public function rules(): array
    {
        return [
            'brand' => ['required', 'array'],
            'brand.name' => ['required', 'string', 'min:1', 'max:50'],

            'type' => ['required', 'array'],
            'type.name' => ['required', 'string', 'min:1', 'max:50'],

            'model' => ['required', 'array'],
            'model.name' => ['required', 'string', 'min:1', 'max:255'],
            'model.seats' => ['required', 'integer', 'min:1', 'max:9'],

            'color' => ['required', 'array'],
            'color.name' => ['required', 'string', 'min:1', 'max:50'],
            'color.hex_code' => ['required', 'string', 'size:7', 'regex:/^#[0-9A-F]{6}$/'],

            'carregistration' => ['required', 'string', 'regex:/^[A-Z0-9]{2,12}$/'],
        ];
    }
}
