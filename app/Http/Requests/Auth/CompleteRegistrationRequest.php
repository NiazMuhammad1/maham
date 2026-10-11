<?php

namespace App\Http\Requests\Auth;

class CompleteRegistrationRequest extends RegistrationStepRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
