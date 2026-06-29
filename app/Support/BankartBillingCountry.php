<?php

namespace App\Support;

/**
 * Bankart customer.billingCountry must be ISO 3166-1 alpha-2 (/^[A-Z]{2}$/).
 * UX value "OTHER" is not valid for the gateway.
 */
final class BankartBillingCountry
{
    public const INVALID_PLACEHOLDER = 'OTHER';

    public static function normalize(?string $country): ?string
    {
        $code = strtoupper(trim((string) $country));

        if ($code === '' || $code === self::INVALID_PLACEHOLDER) {
            return null;
        }

        if (! preg_match('/^[A-Z]{2}$/', $code)) {
            return null;
        }

        return $code;
    }

    public static function isValidForBankart(?string $country): bool
    {
        return self::normalize($country) !== null;
    }

    /**
     * Resolve billingCountry for Bankart JSON. Empty input defaults to ME; invalid returns null.
     */
    public static function resolveForPayload(?string $country, string $defaultWhenEmpty = 'ME'): ?string
    {
        $raw = trim((string) $country);
        if ($raw === '') {
            return $defaultWhenEmpty;
        }

        return self::normalize($raw);
    }
}
