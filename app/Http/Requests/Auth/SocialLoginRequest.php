<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SocialLoginRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'in:google,apple'],
            'id_token' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:100'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'fcm_token' => ['nullable', 'string', 'max:500'],
            'platform' => ['nullable', 'required_with:fcm_token', 'in:android,ios,web'],
        ];
    }
}
