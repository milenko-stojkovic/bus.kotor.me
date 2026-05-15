<?php

namespace App\Support;

/**
 * Relative paths on the private local disk that may be served as FZBR attachment previews.
 *
 * @see \App\Http\Controllers\Panel\FzbrController (stores under free-reservation-requests/{requestId}/)
 */
final class FzbrAttachmentPreviewPath
{
    private const ALLOWED_PREFIXES = [
        'free-reservation-requests/',
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
