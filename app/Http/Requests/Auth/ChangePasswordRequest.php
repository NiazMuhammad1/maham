<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->user()->is_guest;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Social-only accounts have no password yet, so they may set one without the current password.
            'current_password' => [
                Rule::requiredIf($this->user()->password !== null),
                'nullable',
                'string',
                'current_password:sanctum',
            ],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ];
    }
}
