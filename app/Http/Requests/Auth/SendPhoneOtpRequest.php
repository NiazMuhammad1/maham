<?php

namespace App\Http\Requests\Auth;

use App\Services\SaudiPhone;
use Illuminate\Foundation\Http\FormRequest;

class SendPhoneOtpRequest extends FormRequest
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
        ];
    }
}
