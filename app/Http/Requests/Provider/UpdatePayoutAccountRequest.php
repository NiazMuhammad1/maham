<?php

namespace App\Http\Requests\Provider;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Payout Settings": the provider's Saudi bank account for payouts.
 */
class UpdatePayoutAccountRequest extends FormRequest
{
    /**
     * Only approved providers (checked in the controller).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Accept "sa03 8000 0000 6080 1016 7519" as typed: remove spaces and use capital letters.
     */
    protected function prepareForValidation(): void
    {
        foreach (['iban', 'swift_code'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => strtoupper(str_replace(' ', '', $this->input($field)))]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bank_id' => ['required', 'integer', Rule::exists('banks', 'id')->where('is_active', true)],
            'account_holder_name' => ['required', 'string', 'max:100'],

            // Saudi IBAN: "SA" + 22 digits = 24 characters
            'iban' => ['required', 'string', 'regex:/^SA\d{22}$/'],

            // SWIFT / BIC: 8 or 11 characters, e.g. RJHISARI. Empty = the selected bank's code.
            'swift_code' => ['nullable', 'string', 'regex:/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'iban.regex' => __('messages.invalid_iban'),
        ];
    }
}
