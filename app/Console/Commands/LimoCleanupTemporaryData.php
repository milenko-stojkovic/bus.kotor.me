<?php

namespace App\Console\Commands;

use App\Models\LimoPlateUpload;
use App\Models\LimoQrToken;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Removes stale Limo temporary data: old QR token rows and expired unconsumed plate photo uploads.
 * Does not touch limo_pickup_events, limo_pickup_photos, or files under limo_pickup_evidence/.
 */
class LimoCleanupTemporaryData extends Command
{
    protected $signature = 'limo:cleanup-temporary-data';

    protected $description = 'Delete expired unused limo_qr_tokens and expired unconsumed limo_plate_uploads (with files)';

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

        return self::SUCCESS;
    }
}
