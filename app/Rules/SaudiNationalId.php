<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Saudi National ID (starts with 1) or Iqama / residency number (starts with 2):
 * 10 digits with a Luhn check digit.
 */
class SaudiNationalId implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/^[12]\d{9}$/', $value) !== 1 || ! $this->passesChecksum($value)) {
            $fail('messages.national_id_invalid')->translate();
        }
    }

    private function passesChecksum(string $value): bool
    {
        $sum = 0;

        foreach (str_split($value) as $index => $digit) {
            $digit = (int) $digit;

            if ($index % 2 === 0) {
                $digit *= 2;
                $digit = $digit > 9 ? $digit - 9 : $digit;
            }

            $sum += $digit;
        }

        return $sum % 10 === 0;
    }
}
