<?php

namespace App\Models\Concerns;

trait HasLocalizedAttributes
{
    /**
     * Read the `{attribute}_ar` / `{attribute}_en` column matching the current app locale.
     */
    public function localized(string $attribute): ?string
    {
        $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';

        return $this->getAttribute("{$attribute}_{$locale}") ?? $this->getAttribute("{$attribute}_en");
    }
}
