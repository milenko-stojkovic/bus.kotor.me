<?php

namespace App\Support;

/**
 * Relative paths on the private local disk that may be served as Limo incident evidence previews.
 *
 * @see \App\Services\Limo\LimoIncidentService (stores under limo_incidents/{uuid}/)
 */
final class LimoIncidentEvidencePreviewPath
{
    private const ALLOWED_PREFIXES = [
        'limo_incidents/',
    ];

    public static function isAllowedRelativePath(string $relative): bool
    {
        if ($relative === '' || str_contains($relative, '..')) {
            return false;
        }

        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($relative, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
