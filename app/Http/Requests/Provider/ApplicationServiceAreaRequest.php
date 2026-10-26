<?php

namespace App\Http\Requests\Provider;

use Illuminate\Validation\Rule;

/**
 * "Choose Service Area": city, district (optional, empty = whole city) and postal code.
 */
class ApplicationServiceAreaRequest extends ProviderApplicationRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'city_id' => ['required', 'integer', Rule::exists('cities', 'id')->where('is_active', true)],
            'district_id' => [
                'nullable',
                'integer',
                Rule::exists('districts', 'id')->where('is_active', true)->where('city_id', $this->integer('city_id')),
            ],
            'postal_code' => ['nullable', 'string', 'regex:/^\d{5}$/'],
        ];
    }
}
