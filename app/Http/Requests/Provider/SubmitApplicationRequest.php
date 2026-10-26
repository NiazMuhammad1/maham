<?php

namespace App\Http\Requests\Provider;

/**
 * "Upload Business Logo": send `logo` for "Submit details", or nothing for "Skip".
 */
class SubmitApplicationRequest extends ProviderApplicationRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
