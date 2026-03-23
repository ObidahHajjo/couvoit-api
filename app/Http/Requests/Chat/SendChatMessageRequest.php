<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate chat message submission payloads.
 */
class SendChatMessageRequest extends FormRequest
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
     * Get the validation rules for the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'subject' => ['sometimes', 'nullable', 'string', 'max:255'],
            'message' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
