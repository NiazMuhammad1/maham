<?php

namespace App\Http\Requests\Concerns;

use App\Services\SaudiPhone;

trait NormalizesPhone
{
    /**
     * Accept 05XXXXXXXX, 5XXXXXXXX or +9665XXXXXXXX and validate the 9 digit form.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge(['phone' => SaudiPhone::normalize((string) $this->input('phone'))]);
        }
    }

    /**
     * @return array<int, mixed>
     */
    protected function phoneRules(): array
    {
        return ['required', 'string', 'regex:/^5\d{8}$/'];
    }
}
