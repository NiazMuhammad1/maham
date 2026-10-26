<?php

namespace App\Http\Requests\Provider;

/**
 * "Business Details": what customers see on the provider's profile.
 */
class ApplicationBusinessDetailsRequest extends ProviderApplicationRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:100'],
            'tagline' => ['required', 'string', 'max:150'],
            'skills_experience' => ['required', 'string', 'max:2000'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
        ];
    }
}
