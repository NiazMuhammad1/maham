<?php

namespace App\Http\Requests\Auth;

use App\Services\SaudiPhone;
use Illuminate\Foundation\Http\FormRequest;

class VerifyPhoneOtpRequest extends FormRequest
{
    /**
     * Accept 05XXXXXXXX, 5XXXXXXXX or +9665XXXXXXXX and turn it into 5XXXXXXXX.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => SaudiPhone::normalize((string) $this->input('phone'))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^5\d{8}$/'],
            'otp' => ['required', 'string', 'digits:'.config('otp.length')],
            'device_name' => ['nullable', 'string', 'max:100'],
            'fcm_token' => ['nullable', 'string', 'max:500'],
            'platform' => ['nullable', 'required_with:fcm_token', 'in:android,ios,web'],
        ];
    }
}
