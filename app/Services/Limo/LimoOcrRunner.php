<?php

namespace App\Services\Limo;

interface LimoOcrRunner
{
    /**
     * @return string raw OCR text (may contain noise)
     */
    public function run(string $absoluteImagePath, int $timeoutSeconds): string;
}

