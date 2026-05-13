<?php

namespace App\Services\Limo;

use Illuminate\Support\Facades\Storage;

/**
 * Persists copies of images passed to Tesseract when local diagnostics are enabled.
 * Files live under the app private disk: limo_ocr_debug/{upload_token_suffix}/…
 *
 * Cleanup: {@see purgeExpired} (TTL) and {@see deleteRunFolder} (per OCR run when debug save is off).
 * Uses the configured private `local` disk (same root as {@see saveVariantCopy}) so tests with Storage::fake stay consistent.
 */
final class LimoOcrDebugImages
{
    /**
     * Remove the debug folder for one OCR run (last token suffix segment). Safe no-op if missing.
     */
    public static function deleteRunFolder(string $uploadTokenSuffix): void
    {
        $suffix = preg_replace('/[^a-zA-Z0-9_-]+/', '', $uploadTokenSuffix);
        if ($suffix === '') {
            return;
        }
        $rel = 'limo_ocr_debug/'.$suffix;
        $disk = Storage::disk('local');
        if ($disk->exists($rel)) {
            $disk->deleteDirectory($rel);
        }
    }

    /**
     * Delete immediate subfolders of limo_ocr_debug/ older than TTL (directory mtime).
     */
    public static function purgeExpired(int $ttlMinutes): void
    {
        if ($ttlMinutes <= 0) {
            return;
        }
        $disk = Storage::disk('local');
        $base = 'limo_ocr_debug';
        $root = $disk->path($base);
        if (! is_dir($root)) {
            return;
        }
        $maxAge = $ttlMinutes * 60;
        $now = time();
        foreach (scandir($root) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $full = $root.DIRECTORY_SEPARATOR.$name;
            if (! is_dir($full)) {
                continue;
            }
            if ($now - (int) @filemtime($full) > $maxAge) {
                $disk->deleteDirectory($base.'/'.$name);
            }
        }
    }

    /**
     * @return string|null Relative path on the default private "local" disk (e.g. limo_ocr_debug/ab12cd34/original__psm7_wl1.png), or null on failure.
     */
    public static function saveVariantCopy(string $uploadTokenSuffix, string $variantName, int $psm, bool $whitelistEnabled, string $absoluteSourcePath): ?string
    {
        $suffix = preg_replace('/[^a-zA-Z0-9_-]+/', '', $uploadTokenSuffix);
        if ($suffix === '') {
            $suffix = 'unknown';
        }
        $safeVariant = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $variantName);
        $dir = 'limo_ocr_debug/'.$suffix;
        Storage::disk('local')->makeDirectory($dir);

        $ext = strtolower(pathinfo($absoluteSourcePath, PATHINFO_EXTENSION) ?: '');
        if (! in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
            $ext = 'png';
        }

        $wl = $whitelistEnabled ? '1' : '0';
        $relative = $dir.'/'.$safeVariant.'__psm'.$psm.'_wl'.$wl.'.'.$ext;
        $bytes = @file_get_contents($absoluteSourcePath);
        if ($bytes === false) {
            return null;
        }
        if (! Storage::disk('local')->put($relative, $bytes)) {
            return null;
        }

        return $relative;
    }
}
