<?php

namespace App\Http\Requests\Concerns;

trait HasDeviceFields
{
    /**
     * Optional device info sent with every request that issues a token.
     *
     * @return array<string, array<int, string>>
     */
    protected function deviceRules(): array
    {
        return [
            'device_name' => ['nullable', 'string', 'max:100'],
            'fcm_token' => ['nullable', 'string', 'max:500'],
            'platform' => ['nullable', 'required_with:fcm_token', 'in:android,ios,web'],
        ];
    }
}
