<?php

namespace App\Console\Commands;

use App\Models\LimoPlateUpload;
use App\Models\LimoQrToken;
use App\Services\Limo\LimoOcrDebugImages;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Removes stale Limo temporary data: old QR token rows, expired unconsumed plate photo uploads,
 * and expired OCR debug image folders (limo_ocr_debug/* by TTL).
 * Does not touch limo_pickup_events, limo_pickup_photos, or files under limo_pickup_evidence/.
 */
class LimoCleanupTemporaryData extends Command
{
    protected $signature = 'limo:cleanup-temporary-data';

    protected $description = 'Delete stale limo_qr_tokens, expired limo_plate_uploads (with files), and expired limo_ocr_debug folders (TTL)';

    public function handle(): int
    {
        $tz = 'Europe/Podgorica';
        $today = Carbon::today($tz);
        $cutoffDate = $today->toDateString();

        $qrDeleted = LimoQrToken::query()
            ->whereDate('valid_on', '<', $cutoffDate)
            ->delete();

        Log::channel('payments')->info('limo_qr_tokens_cleaned', [
            'deleted_count' => $qrDeleted,
            'cutoff_date' => $cutoffDate,
        ]);

        $this->line("limo_qr_tokens: deleted={$qrDeleted}, cutoff_date={$cutoffDate}");

        $plateRowsDeleted = 0;
        $plateFilesDeleted = 0;

        LimoPlateUpload::query()
            ->where('expires_at', '<', now())
            ->whereNull('consumed_at')
            ->orderBy('id')
            ->chunkById(100, function ($chunk) use (&$plateRowsDeleted, &$plateFilesDeleted): void {
                foreach ($chunk as $upload) {
                    $path = $upload->path;
                    if ($path !== '' && Storage::disk('local')->exists($path)) {
                        Storage::disk('local')->delete($path);
                        $plateFilesDeleted++;
                    }
                    $upload->delete();
                    $plateRowsDeleted++;
                }
            });

        Log::channel('payments')->info('limo_plate_uploads_cleaned', [
            'deleted_count' => $plateRowsDeleted,
            'deleted_files_count' => $plateFilesDeleted,
        ]);

        $this->line("limo_plate_uploads: rows_deleted={$plateRowsDeleted}, files_deleted={$plateFilesDeleted}");

        $ocrDebugTtl = (int) config('limo.ocr.debug_image_ttl_minutes', 60);
        LimoOcrDebugImages::purgeExpired($ocrDebugTtl);
        Log::channel('payments')->info('limo_ocr_debug_ttl_purge_ran', [
            'ttl_minutes' => $ocrDebugTtl,
        ]);
        $this->line("limo_ocr_debug: ttl_purge_ran_minutes={$ocrDebugTtl}");

        return self::SUCCESS;
    }
}
