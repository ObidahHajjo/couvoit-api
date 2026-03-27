<?php

namespace App\Http\Requests\Trip;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Reserve trip request.
 *
 * Notes:
 * - In your controller, non-admin users reserve for themselves, but the request
 *   still validates `person_id`. If you want, you can make it optional for non-admins
 *   later; for now we document the current validation.
 */
class ReserveTripRequest extends FormRequest
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
     * Get the validation rules for reservation requests.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'person_id' => ['sometimes', 'integer', 'exists:persons,id'],
        ];
    }
}
