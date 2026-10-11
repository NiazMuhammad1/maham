<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\HasDeviceFields;
use Illuminate\Foundation\Http\FormRequest;

class GuestLoginRequest extends FormRequest
{
    use HasDeviceFields;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->deviceRules();
    }
}
