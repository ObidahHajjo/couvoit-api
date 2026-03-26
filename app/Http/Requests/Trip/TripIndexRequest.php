<?php

namespace App\Http\Requests\Trip;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Trip index/search request (query parameters).
 */
class TripIndexRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $tripDate = $this->input('tripdate');

        if (! is_string($tripDate)) {
            return;
        }

        $normalized = $this->normalizeTripDate($tripDate);

        if ($normalized !== null) {
            $this->merge(['tripdate' => $normalized]);
        }
    }

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
            'startingcity' => ['nullable', 'string', 'max:255'],
            'arrivalcity'  => ['nullable', 'string', 'max:255'],
            'tripdate'     => [
                'nullable',
                'string',
                'required_with:triptime',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value)) {
                        $fail("The $attribute is invalid.");

                        return;
                    }

                    if ($this->normalizeTripDate($value) !== null) {
                        return;
                    }

                    $fail("The $attribute must match Y-m-d or Y-m-d H:i.");
                },
            ],
            'triptime'     => ['nullable', 'date_format:H:i'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    private function normalizeTripDate(string $value): ?string
    {
        $value = trim($value);

        $formats = [
            'Y-m-d',
            'Y-m-d H:i',
            'd/m/Y',
            'd/m/Y H:i',
            'Y-m-d\TH:i',
        ];

        foreach ($formats as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $value);
                if ($parsed !== false && $parsed->format($format) === $value) {
                    return str_contains($format, 'H:i')
                        ? $parsed->format('Y-m-d H:i')
                        : $parsed->format('Y-m-d');
                }
            } catch (\Throwable) {
                // Try the next supported format.
            }
        }

        return null;
    }
}
