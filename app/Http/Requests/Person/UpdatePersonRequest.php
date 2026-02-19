<?php

namespace App\Http\Requests\Person;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Update person request.
 *
 * Notes:
 * - Accepts `firstname`/`lastname` aliases and normalizes into `first_name`/`last_name`.
 * - If `pseudo` not provided, generates one from first letter of first_name + last_name.
 * - Lowercases pseudo.
 * - `status` can be ACTIVE or DELETED (your service likely maps this to soft delete behavior).
 */
class UpdatePersonRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize incoming request payload prior to validation.
     */
    protected function prepareForValidation(): void
    {
        $firstName = $this->input('first_name', $this->input('firstname'));
        $lastName  = $this->input('last_name',  $this->input('lastname'));

        $this->merge([
            'first_name' => $firstName,
            'last_name'  => $lastName,
        ]);

        if ($this->filled('pseudo') && is_string($this->input('pseudo'))) {
            $this->merge(['pseudo' => strtolower(trim($this->input('pseudo')))]);
            return;
        }

        if (! empty($firstName) && ! empty($lastName)) {
            $generated = mb_substr((string) $firstName, 0, 1) . $lastName;
            $this->merge(['pseudo' => strtolower(trim($generated))]);
        }
    }

    /**
     * Validation rules.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $personId = $this->route('person')?->id ?? auth()->id();

        return [
            'first_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'last_name'  => ['sometimes', 'nullable', 'string', 'max:100'],
            'phone'      => ['sometimes', 'nullable', 'string', 'max:15'],

            'pseudo' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::unique('persons', 'pseudo')->ignore($personId),
            ],

            'car_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:cars,id',
                Rule::unique('persons', 'car_id')->ignore($personId),
            ],

            'status' => [
                'sometimes',
                'string',
                Rule::in(['ACTIVE', 'DELETED']),
            ],
        ];
    }

    /**
     * Custom validation messages.
     *
     * @return array<string,string>
     */
    public function messages(): array
    {
        return [
            'status.in'     => "Le champ status doit être 'ACTIVE' ou 'DELETED'.",
            'pseudo.unique' => 'Ce pseudo est déjà utilisé.',
            'car_id.exists' => 'La voiture sélectionnée est invalide.',
            'car_id.unique' => 'Cette voiture est déjà associée à un autre utilisateur.',
        ];
    }
}
