<?php

namespace App\Services\Limo;

use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

final class TesseractOcrRunner implements LimoOcrRunner
{
    public function run(string $absoluteImagePath, int $timeoutSeconds, array $options = []): string
    {
        $binary = (string) config('limo.ocr.tesseract_binary', '');
        $binary = trim($binary) !== '' ? trim($binary) : 'tesseract';

        $psm = (int) ($options['psm'] ?? 7);
        if ($psm < 0 || $psm > 13) {
            $psm = 7;
        }

        $useWhitelist = ($options['whitelist'] ?? true) === true;

        $cmd = [
            $binary,
            $absoluteImagePath,
            'stdout',
            '-l',
            'eng',
            '--psm',
            (string) $psm,
        ];

        if ($useWhitelist) {
            $cmd[] = '-c';
            $cmd[] = 'tessedit_char_whitelist=ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        }

        $process = new Process($cmd);
        $process->setTimeout(max(1, $timeoutSeconds));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException(self::buildDiagnosticsForFailedProcess($process, $absoluteImagePath));
        }

        return (string) $process->getOutput();
    }

    /**
     * Human-readable diagnostics for failed Tesseract runs (APP_DEBUG OCR summaries).
     * Uses Symfony's escaped command line; does not embed image bytes.
     */
    public static function buildDiagnosticsForFailedProcess(Process $process, string $absoluteImagePath): string
    {
        $exists = is_file($absoluteImagePath);
        $size = $exists ? (int) filesize($absoluteImagePath) : null;
        $basename = basename($absoluteImagePath);
        $stderr = Str::limit($process->getErrorOutput(), 8000, "\n…(stderr truncated)");
        $stdout = $process->getOutput();
        $stdoutPreview = $stdout === '' ? '(empty)' : Str::limit($stdout, 2500, "\n…(stdout truncated)");
        $cmd = $process->getCommandLine();
        $code = $process->getExitCode();

        return implode("\n", [
            'Tesseract process failed.',
            'command: '.$cmd,
            'exit_code: '.($code === null ? 'null' : (string) $code),
            'input_basename: '.$basename,
            'input_exists: '.($exists ? 'yes' : 'no'),
            'input_size_bytes: '.($size === null ? 'n/a' : (string) $size),
            'stderr:',
            $stderr,
            'stdout_preview:',
            $stdoutPreview,
        ]);
    }
}
