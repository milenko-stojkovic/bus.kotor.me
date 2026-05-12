<?php

namespace App\Services\Limo;

use App\Services\Reservation\DuplicateReservationAttemptService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Advisory OCR only — never authoritative. Returns a conservative suggestion or null.
 */
final class LimoPlateOcrService
{
    public function __construct(
        private readonly LimoOcrRunner $runner,
        private readonly LimoPlateImagePreprocessor $preprocessor,
    ) {}

    public function isRunnable(): bool
    {
        if (! (bool) config('limo.ocr.enabled', false)) {
            return false;
        }

        $binary = trim((string) config('limo.ocr.tesseract_binary', ''));

        return $binary === '' || is_file($binary);
    }

    /**
     * @return array{
     *     suggested_plate: ?string,
     *     raw_text: ?string,
     *     normalized_compact: string,
     *     reason: 'disabled'|'unavailable'|'failed'|'no_candidate'|'ok',
     *     ocr_enabled: bool,
     *     ocr_runner_available: bool,
     *     failure_message: ?string,
     *     selected_variant: ?string,
     *     selected_psm: ?int,
     *     debug_variant_attempts: list<array<string, mixed>>,
     *     early_exit: bool,
     *     variants_count?: int,
     *     attempts_count?: int,
     *     preprocessing_gd_loaded?: bool,
     *     preprocessing_used_fallback?: bool,
     *     preprocessing_ran?: bool,
     *     ocr_used_user_crop?: bool,
     *     ocr_crop_width_px?: ?int,
     *     ocr_crop_height_px?: ?int
     * }
     */
    public function analyze(string $absoluteImagePath, ?string $uploadTokenSuffix = null, ?string $absoluteUserCropPath = null): array
    {
        $ocrEnabled = (bool) config('limo.ocr.enabled', false);
        $binary = trim((string) config('limo.ocr.tesseract_binary', ''));
        $binaryOk = $binary === '' || is_file($binary);
        $runnerAvailable = $ocrEnabled && $binaryOk;
        $suffix = $uploadTokenSuffix ?? '';
        $appDebug = (bool) config('app.debug', false);
        $saveDebugImages = $appDebug && (bool) config('limo.ocr.debug_save_images', false);
        if ($saveDebugImages) {
            LimoOcrDebugImages::purgeExpired((int) config('limo.ocr.debug_image_ttl_minutes', 60));
        }

        $ocrCropMeta = [
            'ocr_used_user_crop' => $absoluteUserCropPath !== null && is_file($absoluteUserCropPath),
            'ocr_crop_width_px' => null,
            'ocr_crop_height_px' => null,
        ];
        if ($ocrCropMeta['ocr_used_user_crop']) {
            $gi = @getimagesize($absoluteUserCropPath);
            if (is_array($gi)) {
                $ocrCropMeta['ocr_crop_width_px'] = $gi[0];
                $ocrCropMeta['ocr_crop_height_px'] = $gi[1];
            }
        }

        if (! $ocrEnabled) {
            return $this->mergeOcrCropMeta($this->emptyAnalysis('disabled', false, false, null), $ocrCropMeta);
        }

        if (! $binaryOk) {
            return $this->mergeOcrCropMeta($this->emptyAnalysis('unavailable', true, false, 'tesseract_binary_not_found'), $ocrCropMeta);
        }

        $maxTotal = (float) config('limo.ocr.max_total_seconds', 15);
        $perPassCap = max(1, (int) config('limo.ocr.per_pass_timeout_seconds', 3));

        $phases = [];
        if ($absoluteUserCropPath !== null && is_file($absoluteUserCropPath)) {
            $phases[] = ['abs' => $absoluteUserCropPath, 'prefix' => 'uc_'];
        }
        $phases[] = ['abs' => $absoluteImagePath, 'prefix' => ''];

        $variantTasks = [];
        $tempDirsToClean = [];
        $preprocessFallback = false;
        foreach ($phases as $phase) {
            try {
                $pack = $this->preprocessor->buildVariants($phase['abs']);
            } catch (Throwable) {
                $preprocessFallback = true;
                $pack = ['variants' => [['name' => 'original', 'path' => $phase['abs']]], 'temp_dir' => null];
            }
            if ($pack['temp_dir'] !== null) {
                $tempDirsToClean[] = $pack['temp_dir'];
            }
            foreach ($pack['variants'] as $v) {
                $variantTasks[] = [
                    'name' => $phase['prefix'].$v['name'],
                    'path' => $v['path'],
                ];
            }
        }

        $variantsBuilt = count($variantTasks);
        $deadline = microtime(true) + max(0.0, $maxTotal);
        $passTimeout = $perPassCap;

        $extendedAttempts = $appDebug && (bool) config('limo.ocr.debug_extended_attempts', false);
        $psmModes = $extendedAttempts ? [6, 7, 8, 11, 13] : [7, 8];
        $whitelistModes = $extendedAttempts ? [true, false] : [true];
        $attemptModes = [];
        foreach ($psmModes as $psmVal) {
            foreach ($whitelistModes as $wlVal) {
                $attemptModes[] = ['psm' => $psmVal, 'whitelist' => $wlVal];
            }
        }

        $attemptRows = [];
        $bestCandidate = null;
        $bestScore = -1;
        $selectedVariant = null;
        $selectedPsm = null;
        $selectedRaw = null;
        $anyProcessOk = false;
        $lastThrowable = null;
        $allRaws = [];
        $earlyStopped = false;
        $stopOcr = false;
        /** @var array<string, true> $corroborated */
        $corroborated = [];

        try {
            foreach ($variantTasks as $v) {
                if ($stopOcr) {
                    break;
                }
                foreach ($attemptModes as $mode) {
                    if (microtime(true) > $deadline) {
                        $stopOcr = true;
                        break;
                    }

                    $psm = (int) $mode['psm'];
                    $useWhitelist = (bool) $mode['whitelist'];
                    $variantName = (string) $v['name'];

                    Log::channel('payments')->info('limo_plate_ocr_variant_attempted', [
                        'upload_token_suffix' => $suffix,
                        'variant' => $variantName,
                        'psm' => $psm,
                        'whitelist' => $useWhitelist,
                    ]);

                    $inputPath = $v['path'];
                    $extras = [];
                    if ($appDebug) {
                        $extras = $this->collectInputMeta($inputPath);
                        $extras['from_user_crop'] = str_starts_with($variantName, 'uc_');
                        if ($saveDebugImages) {
                            $extras['debug_image_path'] = LimoOcrDebugImages::saveVariantCopy(
                                $suffix,
                                $variantName,
                                $psm,
                                $useWhitelist,
                                $inputPath,
                            );
                        } else {
                            $extras['debug_image_path'] = null;
                        }
                    }

                    try {
                        $raw = $this->runner->run($inputPath, $passTimeout, ['psm' => $psm, 'whitelist' => $useWhitelist]);
                        $anyProcessOk = true;
                        $nc = self::compactAlnum($raw);
                        $allRaws[] = $raw;

                        $candsFromRaw = $this->collectNormalizedCandidatesFromOcrText($raw);
                        foreach ($candsFromRaw as $c) {
                            if ($this->isCorroboratingOcrSource($variantName, $psm)) {
                                $corroborated[$c] = true;
                            }
                        }

                        $topPass = null;
                        $topPassScore = -1;
                        foreach ($candsFromRaw as $candPass) {
                            $sp = $this->scoreNormalizedCandidate($candPass);
                            if ($sp > $topPassScore) {
                                $topPassScore = $sp;
                                $topPass = $candPass;
                            }
                        }

                        Log::channel('payments')->info('limo_plate_ocr_variant_succeeded', [
                            'upload_token_suffix' => $suffix,
                            'variant' => $variantName,
                            'psm' => $psm,
                            'whitelist' => $useWhitelist,
                            'normalized_length' => strlen($nc),
                            'raw_length' => strlen($raw),
                            'candidate' => $topPass,
                        ]);

                        foreach ($candsFromRaw as $cand) {
                            $baseScore = $this->scoreNormalizedCandidate($cand);
                            if ($baseScore < 0) {
                                continue;
                            }
                            if (! $this->mayUseOcrCandidate($variantName, $psm, $cand, $corroborated)) {
                                continue;
                            }
                            $bonus = str_starts_with($variantName, 'uc_') ? 650 : 0;
                            $adjusted = $baseScore + $bonus;
                            if ($adjusted > $bestScore) {
                                $bestScore = $adjusted;
                                $bestCandidate = $cand;
                                $selectedVariant = $variantName;
                                $selectedPsm = $psm;
                                $selectedRaw = $raw;
                            }
                        }

                        $attemptRows[] = $this->debugVariantAttemptRow(
                            $variantName,
                            $psm,
                            $useWhitelist,
                            $raw,
                            $nc,
                            $topPass,
                            null,
                            $extras,
                        );

                        if ($bestCandidate !== null && $this->shouldStopOcrEarly($bestCandidate, $this->scoreNormalizedCandidate($bestCandidate))) {
                            $earlyStopped = true;
                            $stopOcr = true;
                            break;
                        }
                    } catch (Throwable $e) {
                        $lastThrowable = $e;
                        $err = Str::limit($e->getMessage(), 16000, "\n…(error truncated)");
                        $attemptRows[] = $this->debugVariantAttemptRow(
                            $variantName,
                            $psm,
                            $useWhitelist,
                            '',
                            '',
                            null,
                            $err,
                            $extras,
                        );
                    }
                }
            }
        } finally {
            foreach (array_unique($tempDirsToClean) as $dir) {
                if ($dir !== null && is_dir($dir)) {
                    LimoPlateImagePreprocessor::rrmdir($dir);
                }
            }
        }

        $normalizedCompact = self::compactAlnum(implode("\n", $allRaws));

        if ($bestCandidate !== null) {
            return $this->mergeOcrCropMeta($this->finalizeOcrDebugPayload([
                'suggested_plate' => $bestCandidate,
                'raw_text' => $selectedRaw,
                'normalized_compact' => $normalizedCompact,
                'reason' => 'ok',
                'ocr_enabled' => true,
                'ocr_runner_available' => true,
                'failure_message' => null,
                'selected_variant' => $selectedVariant,
                'selected_psm' => $selectedPsm,
                'debug_variant_attempts' => $attemptRows,
                'early_exit' => $earlyStopped,
            ], $variantsBuilt, $preprocessFallback), $ocrCropMeta);
        }

        if ($anyProcessOk) {
            $longestRaw = $this->longestRaw($allRaws);

            return $this->mergeOcrCropMeta($this->finalizeOcrDebugPayload([
                'suggested_plate' => null,
                'raw_text' => $longestRaw,
                'normalized_compact' => $normalizedCompact,
                'reason' => 'no_candidate',
                'ocr_enabled' => true,
                'ocr_runner_available' => true,
                'failure_message' => null,
                'selected_variant' => null,
                'selected_psm' => null,
                'debug_variant_attempts' => $attemptRows,
                'early_exit' => $earlyStopped,
            ], $variantsBuilt, $preprocessFallback), $ocrCropMeta);
        }

        if ($lastThrowable !== null) {
            return $this->mergeOcrCropMeta($this->finalizeOcrDebugPayload([
                'suggested_plate' => null,
                'raw_text' => null,
                'normalized_compact' => '',
                'reason' => 'failed',
                'ocr_enabled' => true,
                'ocr_runner_available' => true,
                'failure_message' => $lastThrowable->getMessage(),
                'selected_variant' => null,
                'selected_psm' => null,
                'debug_variant_attempts' => $attemptRows,
                'early_exit' => false,
            ], $variantsBuilt, $preprocessFallback), $ocrCropMeta);
        }

        return $this->mergeOcrCropMeta($this->finalizeOcrDebugPayload([
            'suggested_plate' => null,
            'raw_text' => null,
            'normalized_compact' => '',
            'reason' => 'no_candidate',
            'ocr_enabled' => true,
            'ocr_runner_available' => true,
            'failure_message' => null,
            'selected_variant' => null,
            'selected_psm' => null,
            'debug_variant_attempts' => $attemptRows,
            'early_exit' => false,
        ], $variantsBuilt, $preprocessFallback), $ocrCropMeta);
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @param  array{ocr_used_user_crop: bool, ocr_crop_width_px: ?int, ocr_crop_height_px: ?int}  $meta
     * @return array<string, mixed>
     */
    private function mergeOcrCropMeta(array $analysis, array $meta): array
    {
        return array_merge($analysis, $meta);
    }

    private function isCorroboratingOcrSource(string $variantName, int $psm): bool
    {
        if (str_starts_with($variantName, 'uc_')) {
            return true;
        }
        if ($variantName !== 'original') {
            return true;
        }

        return ! in_array($psm, [11, 13], true);
    }

    /**
     * @param  array<string, true>  $corroborated
     */
    private function mayUseOcrCandidate(string $variantName, int $psm, string $cand, array $corroborated): bool
    {
        if ($this->isCorroboratingOcrSource($variantName, $psm)) {
            return true;
        }

        return (bool) ($corroborated[$cand] ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyAnalysis(
        string $reason,
        bool $ocrEnabled,
        bool $runnerAvailable,
        ?string $failure = null,
    ): array {
        $msg = match ($reason) {
            'disabled' => 'No OCR attempt: LIMO_OCR_ENABLED is false.',
            'unavailable' => 'No OCR attempt: '.($failure ?? 'runner not available').'.',
            default => 'No OCR attempt: '.$reason,
        };

        return [
            'suggested_plate' => null,
            'raw_text' => null,
            'normalized_compact' => '',
            'reason' => $reason,
            'ocr_enabled' => $ocrEnabled,
            'ocr_runner_available' => $runnerAvailable,
            'failure_message' => $failure,
            'selected_variant' => null,
            'selected_psm' => null,
            'debug_variant_attempts' => [$this->debugSyntheticAttemptRow($msg)],
            'early_exit' => false,
            'variants_count' => 0,
            'attempts_count' => 1,
            'preprocessing_gd_loaded' => extension_loaded('gd'),
            'preprocessing_used_fallback' => false,
            'preprocessing_ran' => false,
            'ocr_used_user_crop' => false,
            'ocr_crop_width_px' => null,
            'ocr_crop_height_px' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function finalizeOcrDebugPayload(array $payload, int $variantsBuilt, bool $preprocessFallback): array
    {
        $payload['preprocessing_gd_loaded'] = extension_loaded('gd');
        $payload['preprocessing_used_fallback'] = $preprocessFallback;
        $payload['preprocessing_ran'] = true;
        $payload['variants_count'] = $variantsBuilt;

        $rows = $payload['debug_variant_attempts'] ?? [];
        if ($rows === []) {
            $rows[] = $this->debugSyntheticAttemptRow(
                $this->syntheticNoAttemptsMessage((string) ($payload['reason'] ?? 'no_candidate'), $variantsBuilt, $payload)
            );
        }
        $payload['debug_variant_attempts'] = $rows;
        $payload['attempts_count'] = count($rows);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syntheticNoAttemptsMessage(string $reason, int $variantsBuilt, array $payload): string
    {
        if ($variantsBuilt === 0) {
            return 'No Tesseract attempt: no image variants were built from the upload.';
        }

        return match ($reason) {
            'failed' => 'No successful Tesseract read; last error: '.Str::limit((string) ($payload['failure_message'] ?? 'unknown'), 8000, "\n…(truncated)"),
            'no_candidate' => 'Tesseract did not run or produced no logged passes before this response (possible time budget exhausted before first run, or internal skip). variants_built='.$variantsBuilt,
            default => 'No variant/psm attempt rows were recorded (reason='.$reason.').',
        };
    }

    /**
     * @return array{variant: string, psm: ?int, raw_preview: string, normalized_preview: string, candidate: ?string, error: ?string}
     */
    private function debugSyntheticAttemptRow(string $error): array
    {
        return [
            'variant' => 'none',
            'psm' => null,
            'whitelist_enabled' => null,
            'raw_preview' => '',
            'normalized_preview' => '',
            'candidate' => null,
            'error' => $error,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function collectInputMeta(string $absolutePath): array
    {
        $basename = basename($absolutePath);
        $exists = is_file($absolutePath);
        $sizeBytes = $exists ? (int) filesize($absolutePath) : null;
        $width = null;
        $height = null;
        if ($exists) {
            $info = @getimagesize($absolutePath);
            if (is_array($info)) {
                $width = $info[0];
                $height = $info[1];
            }
        }

        return [
            'input_basename' => $basename,
            'input_exists' => $exists,
            'input_size_bytes' => $sizeBytes,
            'image_width' => $width,
            'image_height' => $height,
        ];
    }

    /**
     * @param  array<string, mixed>  $extras  Optional keys: input_basename, input_exists, input_size_bytes, image_width, image_height, debug_image_path
     * @return array<string, mixed>
     */
    private function debugVariantAttemptRow(
        string $variant,
        int $psm,
        bool $whitelistEnabled,
        string $raw,
        string $normalizedCompact,
        ?string $candidate,
        ?string $error,
        array $extras = [],
    ): array {
        $row = [
            'variant' => $variant,
            'psm' => $psm,
            'whitelist_enabled' => $whitelistEnabled,
            'raw_preview' => $raw === '' ? '' : Str::limit($raw, 120, '…'),
            'normalized_preview' => $normalizedCompact === '' ? '' : Str::limit($normalizedCompact, 120, '…'),
            'candidate' => $candidate,
            'error' => $error,
        ];

        if ($extras !== []) {
            return array_merge($row, $extras);
        }

        return $row;
    }

    private function shouldStopOcrEarly(string $candidate, int $score): bool
    {
        if (preg_match('/^[A-Z]{2}\d{3,4}[A-Z]{1,2}$/', $candidate)) {
            return true;
        }

        $min = (int) config('limo.ocr.early_exit_min_score', 500);

        return $score >= $min;
    }

    /**
     * @param  list<string>  $raws
     */
    private function longestRaw(array $raws): ?string
    {
        $best = null;
        $bestLen = 0;
        foreach ($raws as $r) {
            $len = strlen($r);
            if ($len > $bestLen) {
                $bestLen = $len;
                $best = $r;
            }
        }

        return $best;
    }

    public function suggestPlate(string $absoluteImagePath): ?string
    {
        return $this->analyze($absoluteImagePath)['suggested_plate'];
    }

    public static function compactAlnum(string $text): string
    {
        $upper = strtoupper($text);

        return preg_replace('/[^A-Z0-9]+/u', '', $upper) ?? '';
    }

    /**
     * @return list<string>
     */
    private function collectNormalizedCandidatesFromOcrText(string $ocrText): array
    {
        $compact = self::compactAlnum($ocrText);
        if ($compact === '') {
            return [];
        }

        $found = [];
        $patterns = [
            '/[A-Z]{2}\d{3,4}[A-Z]{2}/',
            '/[A-Z]{2,4}\d{3,5}[A-Z]{0,2}/',
            '/[A-Z]{1,3}\d{2,4}[A-Z]{1,3}/',
            '/[A-Z]{1,3}\d{3,5}/',
        ];
        foreach ($patterns as $re) {
            if (preg_match_all($re, $compact, $m) && isset($m[0])) {
                foreach ($m[0] as $raw) {
                    $n = DuplicateReservationAttemptService::normalizeLicensePlate($raw);
                    if ($n !== '' && $this->passesMinimumPlateShape($n)) {
                        $found[$n] = true;
                    }
                }
            }
        }

        $upper = strtoupper($ocrText);
        $spaced = preg_replace('/[^A-Z0-9]+/u', ' ', $upper) ?? '';
        $tokens = preg_split('/\s+/', trim($spaced)) ?: [];
        foreach ($tokens as $t) {
            $tokCompact = self::compactAlnum($t);
            if ($tokCompact === '') {
                continue;
            }
            $n = DuplicateReservationAttemptService::normalizeLicensePlate($tokCompact);
            if ($n !== '' && $this->passesMinimumPlateShape($n)) {
                $found[$n] = true;
            }
        }

        $plates = array_keys($found);
        $plates = $this->pruneOcrNoiseWrappingStrictPlate($plates);

        return $this->keepOnlyMaximalPlateCandidates($plates);
    }

    /**
     * Remove long OCR garbage strings that merely wrap a shorter strict plate (e.g. XNPG123AB ⊃ PG123AB).
     *
     * @param  list<string>  $plates
     * @return list<string>
     */
    private function pruneOcrNoiseWrappingStrictPlate(array $plates): array
    {
        $plates = array_values(array_unique($plates));
        $out = [];
        foreach ($plates as $c) {
            $noise = false;
            foreach ($plates as $inner) {
                if ($inner === $c || strlen($inner) >= strlen($c)) {
                    continue;
                }
                if (! str_contains($c, $inner)) {
                    continue;
                }
                if (! preg_match('/^[A-Z]{2}\d{3,4}[A-Z]{2}$/', $inner)) {
                    continue;
                }
                $noise = true;
                break;
            }
            if (! $noise) {
                $out[] = $c;
            }
        }

        return $out;
    }

    /**
     * Drop proper substrings of another candidate (e.g. KBP505 ⊂ NKBP505).
     *
     * @param  list<string>  $plates
     * @return list<string>
     */
    private function keepOnlyMaximalPlateCandidates(array $plates): array
    {
        $plates = array_values(array_unique($plates));
        $out = [];
        foreach ($plates as $c) {
            $properSubstring = false;
            foreach ($plates as $other) {
                if ($other !== $c && strlen($other) > strlen($c) && str_contains($other, $c)) {
                    $properSubstring = true;
                    break;
                }
            }
            if (! $properSubstring) {
                $out[] = $c;
            }
        }

        return $out;
    }

    private function passesMinimumPlateShape(string $normalized): bool
    {
        $len = strlen($normalized);
        if ($len < 5 || $len > 10) {
            return false;
        }
        if (preg_match_all('/\d/', $normalized) < 2) {
            return false;
        }
        if (preg_match_all('/[A-Z]/', $normalized) < 2) {
            return false;
        }

        return true;
    }

    private function scoreNormalizedCandidate(string $p): int
    {
        if (! $this->passesMinimumPlateShape($p)) {
            return -1;
        }

        $len = strlen($p);
        $letters = (int) preg_match_all('/[A-Z]/', $p);
        $digits = (int) preg_match_all('/\d/', $p);

        $score = 0;
        if (preg_match('/^[A-Z]{2}\d{3,4}[A-Z]{1,2}$/', $p)) {
            $score += 520;
        }
        if (preg_match('/^[A-Z]{2,4}\d{3,5}[A-Z]{0,2}$/', $p)) {
            $score += 340;
        }
        if (preg_match('/^[A-Z]{1,3}\d{2,4}[A-Z]{1,3}$/', $p)) {
            $score += 210;
        }
        if ($len >= 5 && $len <= 8) {
            $score += (9 - $len) * 14;
        } else {
            $score += 25;
        }
        $score += min($letters, $digits) * 18;
        // Prefer longer plausible plates when regex tiers tie (e.g. NKBP505 vs KBP505).
        $score += $len * 12;

        return $score;
    }
}
