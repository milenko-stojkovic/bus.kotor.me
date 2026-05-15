<?php

namespace App\Services\Limo;

final class ArchiveDerivativeResult
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public readonly string $absolutePath,
        public readonly int $originalBytes,
        public readonly int $archiveBytes,
        public readonly array $options,
    ) {}
}
