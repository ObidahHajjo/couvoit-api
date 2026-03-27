<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Auth request payload (register/login).
 */
class AuthRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        $passwordRules = ['required', 'string'];

        if ($this->is('api/auth/register') || $this->is('auth/register')) {
            $passwordRules[] = Password::min(8)
                ->mixedCase()
                ->numbers()
                ->symbols();
        }

        return [
            'email' => 'required|email',
            'password' => $passwordRules,
        ];
    }
}
