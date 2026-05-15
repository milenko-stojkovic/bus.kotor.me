<?php

namespace App\Services\ExternalArchive;

/**
 * Temporary optimized file to upload to MEGA while retaining original local path metadata.
 */
final class ArchiveDerivativeUpload
{
    /**
     * @param  array<string, mixed>  $derivativeOptions
     */
    public function __construct(
        public readonly string $uploadAbsolutePath,
        public readonly string $derivativeSourcePath,
        public readonly array $derivativeOptions,
        public readonly int $originalBytes,
        public readonly int $archiveBytes,
        public readonly string $generatedExtension = 'jpg',
    ) {}

    public function reductionPercent(): float
    {
        if ($this->originalBytes <= 0) {
            return 0.0;
        }

        return round((1 - ($this->archiveBytes / $this->originalBytes)) * 100, 1);
    }
}
