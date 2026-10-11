<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\HasDeviceFields;
use App\Http\Requests\Concerns\NormalizesPhone;
use Illuminate\Foundation\Http\FormRequest;

class VerifyPhoneOtpRequest extends FormRequest
{
    use HasDeviceFields, NormalizesPhone;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => $this->phoneRules(),
            'otp' => ['required', 'string', 'digits:'.config('otp.length')],
            ...$this->deviceRules(),
        ];
    }
}
