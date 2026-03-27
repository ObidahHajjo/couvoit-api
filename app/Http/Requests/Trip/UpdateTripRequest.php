<?php

namespace App\Http\Requests\Trip;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Update trip request.
 */
class UpdateTripRequest extends FormRequest
{
    /**
     * Determine whether the request is authorized.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for trip updates.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'kms' => ['sometimes', 'numeric', 'gt:0'],
            'trip_datetime' => ['sometimes', 'date', 'after:now'],
            'available_seats' => ['sometimes', 'integer', 'min:1', 'max:9'],
            'smoking_allowed' => ['sometimes', 'boolean'],

            'starting_address' => ['sometimes', 'array'],
            'starting_address.street_number' => ['required_with:starting_address', 'string', 'max:50'],
            'starting_address.street_name' => ['required_with:starting_address', 'string', 'max:255'],
            'starting_address.postal_code' => ['required_with:starting_address', 'string', 'max:20'],
            'starting_address.city_name' => ['required_with:starting_address', 'string', 'max:255'],

            'arrival_address' => ['sometimes', 'array'],
            'arrival_address.street_number' => ['required_with:arrival_address', 'string', 'max:50'],
            'arrival_address.street_name' => ['required_with:arrival_address', 'string', 'max:255'],
            'arrival_address.postal_code' => ['required_with:arrival_address', 'string', 'max:20'],
            'arrival_address.city_name' => ['required_with:arrival_address', 'string', 'max:255'],
        ];
    }
}
