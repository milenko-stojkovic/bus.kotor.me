<?php

namespace App\Support;

/**
 * OCR helper only: regional license plate prefixes used for scoring (not validation).
 *
 * Prefixes are ASCII-normalized (Ž→Z, Š→S, Č/Ć→C, Đ→D).
 */
final class RegionalLicensePlatePrefix
{
    public const GROUP_ME = 'ME';
    public const GROUP_HR = 'HR';
    public const GROUP_RS = 'RS';
    public const GROUP_NONE = 'none';

    /**
     * @return array{prefix:string, groups:list<'ME'|'HR'|'RS'>, score_group:'ME'|'HR'|'RS'|'none'}
     */
    public static function classify(string $normalizedAsciiPlate): array
    {
        $pref = self::detectPrefix($normalizedAsciiPlate);
        if ($pref === null) {
            return ['prefix' => '', 'groups' => [], 'score_group' => self::GROUP_NONE];
        }

        $map = self::prefixToGroups();
        $groups = array_values($map[$pref] ?? []);

        $scoreGroup = self::GROUP_NONE;
        if (in_array(self::GROUP_ME, $groups, true)) {
            $scoreGroup = self::GROUP_ME;
        } elseif (in_array(self::GROUP_HR, $groups, true)) {
            $scoreGroup = self::GROUP_HR;
        } elseif (in_array(self::GROUP_RS, $groups, true)) {
            $scoreGroup = self::GROUP_RS;
        }

        return ['prefix' => $pref, 'groups' => $groups, 'score_group' => $scoreGroup];
    }

    public static function detectPrefix(string $normalizedAsciiPlate): ?string
    {
        if (strlen($normalizedAsciiPlate) < 2) {
            return null;
        }
        $p = substr($normalizedAsciiPlate, 0, 2);

        return isset(self::allPrefixSet()[$p]) ? $p : null;
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

    /**
     * Candidate shape rules (kept flexible for custom ME plates):
     * - starts with known regional prefix (ME/HR/RS)
     * - total length 5..9
     */
    public static function isRegionalPrefixedCandidate(string $normalizedAscii): bool
    {
        $c = self::classify($normalizedAscii);
        if ($c['score_group'] === self::GROUP_NONE) {
            return false;
        }

        $len = strlen($normalizedAscii);

        return $len >= 5 && $len <= 9;
    }

    public static function isMontenegroPrefixedCandidate(string $normalizedAscii): bool
    {
        $c = self::classify($normalizedAscii);
        if ($c['score_group'] !== self::GROUP_ME) {
            return false;
        }

        $len = strlen($normalizedAscii);

        return $len >= 5 && $len <= 9;
    }

    /**
     * @return array<string, true>
     */
    public static function allPrefixSet(): array
    {
        static $set = null;
        if (is_array($set)) {
            return $set;
        }
        $set = [];
        foreach (self::prefixToGroups() as $p => $_) {
            $set[$p] = true;
        }

        return $set;
    }

    /**
     * @return array<string, list<'ME'|'HR'|'RS'>>
     */
    private static function prefixToGroups(): array
    {
        static $map = null;
        if (is_array($map)) {
            return $map;
        }

        $map = [];

        // Montenegro (ME) — existing list
        foreach ([
            // Some OCR outputs may include the country code sticker as a "prefix".
            // Treat it as ME for scoring only (never hard validation).
            'ME',
            'AN', 'BA', 'BD', 'BP', 'BR', 'CT', 'DG', 'GS', 'HN', 'KL', 'KO', 'MK', 'NK', 'PG', 'PL',
            'PT', 'PZ', 'PV', 'RO', 'SN', 'TV', 'TZ', 'UL', 'ZT', 'ZB',
        ] as $p) {
            $map[$p] = [self::GROUP_ME];
        }

        // Croatia (HR) — common two-letter city codes (ASCII-normalized)
        foreach ([
            'ZG', 'ST', 'DU', 'RI', 'OS', 'PU', 'KA', 'ZD', 'SI', 'VU', 'VK', 'SB', 'DJ', 'BM', 'GS',
            'IM', 'KR', 'KT', 'MA', 'NG', 'OG', 'PV', 'SK', 'SL', 'VT', 'NA', 'CB', 'CK', 'DE', 'DA',
            'BP', 'KL', 'PL', 'PS', 'SA', 'TC', 'VU', 'ZD', 'ZU',
        ] as $p) {
            $map[$p] = array_values(array_unique(array_merge($map[$p] ?? [], [self::GROUP_HR])));
        }

        // Serbia (RS) — common two-letter city codes (Latin)
        foreach ([
            'BG', 'NS', 'NI', 'KG', 'SU', 'ZR', 'SM', 'PA', 'SA', 'SD', 'PN', 'PI', 'RU', 'VA', 'UE',
            'CA', 'KV', 'LE', 'ZA', 'JA', 'PO', 'KI', 'AB',
        ] as $p) {
            $map[$p] = array_values(array_unique(array_merge($map[$p] ?? [], [self::GROUP_RS])));
        }

        return $map;
    }
}

