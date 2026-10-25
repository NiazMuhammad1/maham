<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Account Settings > Home Address (same fields as the sign-up address step).
 */
class UpdateAddressRequest extends FormRequest
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
