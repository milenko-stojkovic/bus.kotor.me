<?php

namespace App\Services\ExternalArchive;

/**
 * Resolved private-disk path for an authorized admin preview (local or just re-downloaded from MEGA).
 */
final readonly class TemporaryPreviewFile
{
    public function __construct(
        public string $relativePrivatePath,
        public bool $restoredFromMegaForPreview,
    ) {}
}
