<?php

namespace App\Http\Requests\Auth;

use Illuminate\Validation\Rule;

class AddressRequest extends RegistrationStepRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'city_id' => ['required', 'integer', Rule::exists('cities', 'id')->where('is_active', true)],
            'district_id' => [
                'required',
                'integer',
                Rule::exists('districts', 'id')->where('is_active', true)->where('city_id', $this->integer('city_id')),
            ],
            'street_address' => ['required', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'regex:/^\d{5}$/'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
