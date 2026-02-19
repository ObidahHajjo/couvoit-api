<?php

namespace App\Http\Requests\Trip;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Store trip request.
 */
class StoreTripRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'kms' => ['required', 'numeric', 'gt:0'],
            'trip_datetime' => ['required', 'date'],
            'available_seats' => ['required', 'integer', 'min:1', 'max:9'],
            'smoking_allowed' => ['sometimes', 'boolean'],

            'starting_address' => ['required', 'array'],
            'starting_address.street_number' => ['required', 'string', 'max:50'],
            'starting_address.street_name' => ['required', 'string', 'max:255'],
            'starting_address.postal_code' => ['required', 'string', 'max:20'],
            'starting_address.city_name' => ['required', 'string', 'max:255'],

            'arrival_address' => ['required', 'array'],
            'arrival_address.street_number' => ['required', 'string', 'max:50'],
            'arrival_address.street_name' => ['required', 'string', 'max:255'],
            'arrival_address.postal_code' => ['required', 'string', 'max:20'],
            'arrival_address.city_name' => ['required', 'string', 'max:255'],

            'person_id' => ['sometimes', 'integer', 'exists:persons,id'],
        ];
    }
}
