<?php

namespace App\Services\Limo;

use Symfony\Component\Process\Process;

final class TesseractOcrRunner implements LimoOcrRunner
{
    public function run(string $absoluteImagePath, int $timeoutSeconds): string
    {
        $binary = (string) config('limo.ocr.tesseract_binary', '');
        $binary = trim($binary) !== '' ? trim($binary) : 'tesseract';

        // Tesseract CLI: tesseract <image> stdout -l eng --psm 7
        $process = new Process([
            $binary,
            $absoluteImagePath,
            'stdout',
            '-l',
            'eng',
            '--psm',
            '7',
        ]);
        $process->setTimeout(max(1, $timeoutSeconds));

        $process->run();

        if (! $process->isSuccessful()) {
            // Let caller decide whether this is "unavailable" or "failed" based on exit code/output.
            throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'tesseract failed');
        }

        return (string) $process->getOutput();
    }
}

