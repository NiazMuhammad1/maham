<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\HasDeviceFields;
use Illuminate\Foundation\Http\FormRequest;

class SocialLoginRequest extends FormRequest
{
    use HasDeviceFields;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'in:google,apple'],
            'id_token' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:100'],
            ...$this->deviceRules(),
        ];
    }
}
