<?php

namespace App\Http\Requests\Person;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

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

        if (!empty($firstName) && !empty($lastName)) {
            $generated = mb_substr((string) $firstName, 0, 1) . (string) $lastName;
            $this->merge(['pseudo' => strtolower(trim($generated))]);
        }
    }


    public function rules(): array
    {
        return [
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name'  => ['nullable', 'string', 'max:100'],
            'phone'      => ['nullable', 'string', 'max:15'],
            'pseudo'     => ['nullable', 'string', 'max:50', Rule::unique('persons', 'pseudo')],
            'car_id'     => [
                'nullable',
                'integer',
                'exists:cars,id',
                Rule::unique('persons', 'car_id'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'pseudo.unique' => 'Ce pseudo est déjà utilisé.',
            'car_id.exists' => 'La voiture sélectionnée est invalide.',
            'car_id.unique' => 'Cette voiture est déjà associée à un autre utilisateur.',
        ];
    }
}
