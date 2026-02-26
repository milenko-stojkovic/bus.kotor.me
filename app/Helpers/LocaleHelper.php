<?php

namespace App\Helpers;

/**
 * Mapiranje Accept-Language na podržane locale.
 * cg, hr, sr, bs -> cg (Crnogorski); svi ostali -> en.
 */
class LocaleHelper
{
    public const SUPPORTED = ['en', 'cg'];

    /** Mapira browser locale (npr. hr-HR, sr-Latn) na cg ili en. */
    public static function fromAcceptLanguage(?string $acceptLanguage): string
    {
        if ($acceptLanguage === null || $acceptLanguage === '') {
            return 'en';
        }

        $preferred = explode(',', $acceptLanguage)[0] ?? '';
        $code = strtolower(trim(explode('-', $preferred)[0] ?? ''));

        return in_array($code, ['cg', 'hr', 'sr', 'bs'], true) ? 'cg' : 'en';
    }

    public static function isValid(string $locale): bool
    {
        return in_array($locale, self::SUPPORTED, true);
    }
}
