<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Minimal helper for ui_translations (group/key/locale) with caching.
 *
 * Intended for custom V2 UI only (landing + guest reserve + payment messages).
 */
class UiText
{
    public static function t(string $group, string $key, ?string $fallback = null, ?string $locale = null): string
    {
        $locale = is_string($locale) && $locale !== '' ? $locale : app()->getLocale();
        $text = self::getGroupMap($group, $locale)[$key] ?? null;

        if (is_string($text) && $text !== '') {
            return $text;
        }

        // Fallback to any locale (first row) if missing for requested locale.
        $any = self::getAnyLocale($group, $key);
        if (is_string($any) && $any !== '') {
            return $any;
        }

        return $fallback ?? ($group.'.'.$key);
    }

    /**
     * @return array<string, string>
     */
    private static function getGroupMap(string $group, string $locale): array
    {
        $cacheKey = 'ui_translations:group='.$group.':locale='.$locale;

        /** @var array<string, string> $map */
        $map = Cache::remember($cacheKey, now()->addHours(6), function () use ($group, $locale): array {
            return DB::table('ui_translations')
                ->where('group', $group)
                ->where('locale', $locale)
                ->pluck('text', 'key')
                ->map(fn ($v) => is_string($v) ? $v : (string) $v)
                ->all();
        });

        return $map;
    }

    private static function getAnyLocale(string $group, string $key): ?string
    {
        $cacheKey = 'ui_translations:any:group='.$group.':key='.$key;

        /** @var string|null $text */
        $text = Cache::remember($cacheKey, now()->addHours(6), function () use ($group, $key): ?string {
            $row = DB::table('ui_translations')
                ->where('group', $group)
                ->where('key', $key)
                ->value('text');

            return is_string($row) ? $row : (is_null($row) ? null : (string) $row);
        });

        return $text;
    }
}

