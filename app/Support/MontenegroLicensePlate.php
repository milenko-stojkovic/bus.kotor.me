<?php

namespace App\Support;

final class MontenegroLicensePlate
{
    /**
     * Valid Montenegro registration area prefixes (ASCII-normalized).
     *
     * Notes:
     * - PZ represents PŽ
     * - SN represents ŠN
     * - ZB represents ŽB
     *
     * @return array<string, true>
     */
    public static function prefixSet(): array
    {
        static $set = null;
        if (is_array($set)) {
            return $set;
        }

        $prefixes = [
            'AN', 'BA', 'BD', 'BP', 'BR', 'CT', 'DG', 'GS', 'HN', 'KL', 'KO', 'MK', 'NK', 'PG', 'PL',
            'PT', 'PZ', 'PV', 'RO', 'SN', 'TV', 'TZ', 'UL', 'ZT', 'ZB',
        ];

        $set = [];
        foreach ($prefixes as $p) {
            $set[$p] = true;
        }

        return $set;
    }

    /**
     * ASCII-normalize a raw plate candidate (upper, strip non A–Z0–9, transliterate diacritics).
     */
    public static function normalizeAscii(string $raw): string
    {
        $v = strtoupper(trim($raw));
        $v = strtr($v, [
            'Ž' => 'Z',
            'Š' => 'S',
            'Č' => 'C',
            'Ć' => 'C',
            'Đ' => 'D',
        ]);
        $v = preg_replace('/\s+/', '', $v) ?? $v;
        $v = preg_replace('/[^A-Z0-9]/', '', $v) ?? $v;

        return $v;
    }

    public static function detectPrefix(string $normalizedAscii): ?string
    {
        if (strlen($normalizedAscii) < 2) {
            return null;
        }
        $pref = substr($normalizedAscii, 0, 2);

        return isset(self::prefixSet()[$pref]) ? $pref : null;
    }

    /**
     * CG-prefixed candidate rules (kept flexible for custom plates):
     * - starts with a valid CG prefix
     * - total length 5..9
     */
    public static function isCgPrefixedCandidate(string $normalizedAscii): bool
    {
        $pref = self::detectPrefix($normalizedAscii);
        if ($pref === null) {
            return false;
        }

        $len = strlen($normalizedAscii);

        return $len >= 5 && $len <= 9;
    }
}

