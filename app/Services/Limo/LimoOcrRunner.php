<?php

namespace App\Services\Limo;

interface LimoOcrRunner
{
    /**
     * Run Tesseract on an image file.
     *
     * @param  array{psm?: int, whitelist?: bool}  $options  psm default 7; whitelist default true (A–Z0–9 only when true)
     * @return string raw OCR text (may contain noise)
     */
    public function run(string $absoluteImagePath, int $timeoutSeconds, array $options = []): string;
}
