<?php

namespace App\Http\Requests\Person;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Update role request (admin).
 */
class UpdateRolePersonRequest extends FormRequest
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
     * Get the validation rules for role reassignment.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'person_id' => ['required', 'string'],
            'role_id'   => ['required', 'integer', 'exists:roles,id'],
        ];
    }
}
