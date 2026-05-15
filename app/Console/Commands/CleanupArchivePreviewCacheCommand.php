<?php

namespace App\Console\Commands;

use App\Models\ExternalFileArchive;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes temporary on-disk files re-downloaded from MEGA for admin preview (TTL).
 */
class CleanupArchivePreviewCacheCommand extends Command
{
    protected $signature = 'files:cleanup-preview-cache';

    protected $description = 'Remove expired MEGA preview re-downloads (keeps archive rows; only when local_deleted_at is set)';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $deletedFiles = 0;
        $rows = ExternalFileArchive::query()
            ->where('status', ExternalFileArchive::STATUS_UPLOADED)
            ->whereNotNull('local_deleted_at')
            ->whereNotNull('preview_expires_at')
            ->where('preview_expires_at', '<=', now())
            ->get();

        foreach ($rows as $archive) {
            $path = (string) $archive->original_local_path;
            if ($path !== '' && ! str_contains($path, '..') && $disk->exists($path)) {
                $disk->delete($path);
                $deletedFiles++;
            }
            $archive->update([
                'preview_restored_at' => null,
                'preview_expires_at' => null,
            ]);
        }

        Log::channel('payments')->info('external_archive_preview_cache_cleanup', [
            'rows_processed' => $rows->count(),
            'files_deleted' => $deletedFiles,
        ]);

        $this->info("Processed {$rows->count()} archive row(s), deleted {$deletedFiles} preview file(s).");

        return self::SUCCESS;
    }
}
