<?php

namespace App\Support;

/**
 * Relative paths on the private local disk that may be served as Limo plate pickup evidence previews.
 */
final class LimoPlateEvidencePreviewPath
{
    /**
     * Production plate photos live under limo_pickup_evidence/{eventId}/ (see LimoPlatePickupService).
     * limo_pickup_photos/ is retained for legacy rows or tests that mirror older layouts.
     */
    private const ALLOWED_PREFIXES = [
        'limo_pickup_evidence/',
        'limo_pickup_photos/',
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
