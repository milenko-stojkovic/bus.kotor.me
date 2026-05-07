<?php

namespace App\Services\Limo;

use App\Services\Reservation\DuplicateReservationAttemptService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Advisory OCR only — never authoritative. Returns a conservative suggestion or null.
 */
final class LimoPlateOcrService
{
    public function __construct(
        private readonly LimoOcrRunner $runner,
    ) {}

    /**
     * @return string|null normalized license plate candidate, or null if unavailable / uncertain
     */
    public function suggestPlate(string $absoluteImagePath): ?string
    {
        if (! (bool) config('limo.ocr.enabled', false)) {
            Log::channel('payments')->info('limo_plate_ocr_unavailable', [
                'reason' => 'disabled',
                'path_suffix' => basename($absoluteImagePath),
            ]);

            return null;
        }

        $binary = (string) config('limo.ocr.tesseract_binary', '');
        if (trim($binary) !== '' && ! is_file($binary)) {
            Log::channel('payments')->warning('limo_plate_ocr_unavailable', [
                'reason' => 'binary_not_found',
                'binary' => $binary,
                'path_suffix' => basename($absoluteImagePath),
            ]);

            return null;
        }

        $timeout = (int) config('limo.ocr.timeout_seconds', 10);

        try {
            $rawText = $this->runner->run($absoluteImagePath, max(1, $timeout));
            $candidate = $this->extractPlateCandidate($rawText);
            return $candidate;
        } catch (Throwable $e) {
            $msg = strtolower($e->getMessage());
            $isUnavailable = str_contains($msg, 'not found') || str_contains($msg, 'no such file') || str_contains($msg, 'tesseract');

            Log::channel('payments')->warning($isUnavailable ? 'limo_plate_ocr_unavailable' : 'limo_plate_ocr_failed', [
                'path_suffix' => basename($absoluteImagePath),
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return null;
        }
    }

    private function extractPlateCandidate(string $ocrText): ?string
    {
        $upper = strtoupper($ocrText);
        $compact = preg_replace('/[^A-Z0-9]+/u', '', $upper) ?? '';

        // First pass: look for a "plate-like" alnum run.
        // Prefer stricter patterns to avoid swallowing surrounding noise (e.g. "...PG123AB..." inside longer text).
        $candidates = [];
        foreach ([
            3 => '/[A-Z]{2}\d{3,4}[A-Z]{2}/',     // strict: PG123AB / PG1234AB
            2 => '/[A-Z]{1,3}\d{2,4}[A-Z]{1,3}/', // medium: KO123AB
            1 => '/[A-Z]{1,3}\d{3,5}/',           // loose: letters+digits
        ] as $weight => $re) {
            if (preg_match_all($re, $compact, $m) && isset($m[0])) {
                foreach ($m[0] as $raw) {
                    $candidates[] = [$raw, $weight];
                }
            }
        }

        // Second pass: token-level candidates (already separated by OCR whitespace).
        $spaced = preg_replace('/[^A-Z0-9]+/u', ' ', $upper) ?? '';
        $tokens = preg_split('/\s+/', trim($spaced)) ?: [];
        foreach ($tokens as $t) {
            if ($t !== '') {
                $candidates[] = [$t, 0];
            }
        }

        $best = null;
        $bestScore = null;
        foreach ($candidates as $row) {
            [$t, $weight] = $row;
            if ($t === '') {
                continue;
            }

            $normalized = DuplicateReservationAttemptService::normalizeLicensePlate($t);
            if ($normalized === '') {
                continue;
            }

            $len = strlen($normalized);
            if ($len < 5 || $len > 10) {
                continue;
            }

            // Conservative: require at least 2 digits (plates are alnum, usually include numbers).
            if (preg_match_all('/\d/', $normalized) < 2) {
                continue;
            }

            $score = ($weight * 100) + $len;
            if ($best === null || $bestScore === null || $score > $bestScore) {
                $best = $normalized;
                $bestScore = $score;
            }
        }

        return $best;
    }
}
