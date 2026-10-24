<?php

namespace App\Services;

/**
 * Chat safety (client requirement SEC-05): keep customers and providers on the platform.
 *
 * - Phone numbers written in a message are hidden ("05 12 34 56 78" -> "**********").
 * - Words about paying or talking outside the app add a warning message to the thread.
 */
class ChatFilter
{
    /** What a hidden phone number looks like in the message. */
    public const string MASK = '**********';

    /**
     * Words that add the safety warning (English and Arabic, matched without caring about upper/lower case).
     *
     * @var list<string>
     */
    public const array BLOCKED_WORDS = [
        'cash',
        'transfer',
        'off-platform',
        'off platform',
        'discount outside',
        'my number',
        'whatsapp',
        'كاش',
        'نقدا',
        'نقداً',
        'تحويل',
        'رقمي',
        'واتساب',
        'واتس',
    ];

    /**
     * Hide phone numbers: 7 or more digits (English, Arabic ٠-٩ or Persian ۰-۹), optionally starting
     * with "+" and split by spaces, dashes, dots or brackets. Short numbers like prices ("250") stay.
     */
    public static function maskPhoneNumbers(string $text): string
    {
        $digit = '[0-9٠-٩۰-۹]';

        return preg_replace("/\+?{$digit}(?:[\s\-.()]*{$digit}){6,}/u", self::MASK, $text);
    }

    public static function hasBlockedWords(string $text): bool
    {
        $text = mb_strtolower($text);

        foreach (self::BLOCKED_WORDS as $word) {
            if (str_contains($text, $word)) {
                return true;
            }
        }

        return false;
    }
}
