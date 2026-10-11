<?php

namespace App\Services;

class SaudiPhone
{
    public const string COUNTRY_CODE = '+966';

    /**
     * Normalize any common Saudi mobile format (05XXXXXXXX, 5XXXXXXXX, +9665XXXXXXXX,
     * 009665XXXXXXXX, Arabic-Indic digits) to the 9 digit national form 5XXXXXXXX.
     * Returns the input digits unchanged when they do not look like a Saudi mobile.
     */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', strtr($phone, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]));

        $national = preg_replace('/^(00966|966|0)/', '', $digits);

        return self::isValid($national) ? $national : $digits;
    }

    public static function isValid(?string $phone): bool
    {
        return $phone !== null && preg_match('/^5\d{8}$/', $phone) === 1;
    }

    /**
     * International format expected by SMS gateways, e.g. 966512345678.
     */
    public static function international(string $nationalPhone): string
    {
        return ltrim(self::COUNTRY_CODE, '+').$nationalPhone;
    }
}
