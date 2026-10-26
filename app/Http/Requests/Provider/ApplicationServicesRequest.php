<?php

namespace App\Http\Requests\Provider;

use Illuminate\Validation\Rule;

/**
 * "Select Service": the categories the provider receives broadcast requests for.
 */
class ApplicationServicesRequest extends ProviderApplicationRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('services', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
        ];
    }
}
