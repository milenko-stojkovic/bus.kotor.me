<?php

namespace App\Services\ExternalArchive;

use App\Contracts\MegaArchiveClient;
use App\Models\ExternalFileArchive;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class ExternalFileArchiveService
{
    public function __construct(
        private readonly MegaArchiveClient $megaClient,
    ) {}

    /**
     * Archive one private disk file to MEGA. Never deletes local file unless upload succeeds and DB row is updated.
     */
    public function archiveLocalPrivateFile(
        string $sourceTable,
        int $sourceId,
        ?string $sourceColumn,
        string $localPath,
        ?string $contextType = null,
        ?ArchiveDerivativeUpload $derivativeUpload = null,
    ): ExternalFileArchive {
        $disk = Storage::disk('local');

        $existingUploaded = ExternalFileArchive::query()
            ->where('source_table', $sourceTable)
            ->where('source_id', $sourceId)
            ->where('source_column', $sourceColumn)
            ->where('status', ExternalFileArchive::STATUS_UPLOADED)
            ->orderByDesc('id')
            ->first();
        if ($existingUploaded instanceof ExternalFileArchive) {
            return $existingUploaded;
        }

        if (! $disk->exists($localPath)) {
            throw new \InvalidArgumentException('Local file does not exist on private disk: '.$localPath);
        }

        $generated = ArchiveFilenameGenerator::generate(
            $contextType,
            $sourceTable,
            $sourceId,
            $sourceColumn,
            $localPath,
            $derivativeUpload?->generatedExtension,
        );

        $absolute = $derivativeUpload !== null
            ? $derivativeUpload->uploadAbsolutePath
            : $disk->path($localPath);

        $derivativeTempPath = $derivativeUpload?->uploadAbsolutePath;

        Log::channel('payments')->info('external_archive_upload_started', [
            'source_table' => $sourceTable,
            'source_id' => $sourceId,
            'source_column' => $sourceColumn,
            'context_type' => $contextType,
            'generated_file_name' => $generated,
            'original_local_path' => $localPath,
            'archived_derivative' => $derivativeUpload !== null,
        ]);

        if ($derivativeUpload !== null) {
            Log::channel('payments')->info('external_archive_derivative_prepared', [
                'source_table' => $sourceTable,
                'source_id' => $sourceId,
                'original_local_path' => $localPath,
                'derivative_source_path' => $derivativeUpload->derivativeSourcePath,
                'original_bytes' => $derivativeUpload->originalBytes,
                'archive_bytes' => $derivativeUpload->archiveBytes,
                'reduction_percent' => $derivativeUpload->reductionPercent(),
                'derivative_options' => $derivativeUpload->derivativeOptions,
            ]);
        }

        /** @var ExternalFileArchive $row */
        $row = ExternalFileArchive::query()->create([
            'source_table' => $sourceTable,
            'source_id' => $sourceId,
            'source_column' => $sourceColumn,
            'context_type' => $contextType,
            'archive_provider' => ExternalFileArchive::PROVIDER_MEGA,
            'generated_file_name' => $generated,
            'mega_node_id' => null,
            'mega_path' => null,
            'original_local_path' => $localPath,
            'archived_derivative' => $derivativeUpload !== null,
            'derivative_source_path' => $derivativeUpload?->derivativeSourcePath,
            'derivative_options' => $derivativeUpload?->derivativeOptions,
            'local_deleted_at' => null,
            'archived_at' => null,
            'status' => ExternalFileArchive::STATUS_PENDING,
            'error_message' => null,
        ]);

        try {
            $upload = $this->megaClient->uploadLocalFile($absolute, $generated);
        } finally {
            if ($derivativeTempPath !== null && is_file($derivativeTempPath)) {
                @unlink($derivativeTempPath);
            }
        }

        if (! $upload->ok) {
            $row->update([
                'status' => ExternalFileArchive::STATUS_FAILED,
                'error_message' => $upload->error ?? 'upload_failed',
            ]);
            Log::channel('payments')->warning('external_archive_upload_failed', [
                'external_file_archive_id' => $row->id,
                'source_table' => $sourceTable,
                'source_id' => $sourceId,
                'error' => $upload->error,
            ]);

            return $row->refresh();
        }

        try {
            DB::transaction(function () use ($row, $upload): void {
                $row->update([
                    'status' => ExternalFileArchive::STATUS_UPLOADED,
                    'mega_node_id' => $upload->megaNodeId,
                    'mega_path' => $upload->megaPath,
                    'archived_at' => now(),
                    'error_message' => null,
                ]);
            });
        } catch (Throwable $e) {
            $row->update([
                'status' => ExternalFileArchive::STATUS_FAILED,
                'error_message' => 'db_update_failed: '.$e->getMessage(),
            ]);
            Log::channel('payments')->error('external_archive_upload_db_failed', [
                'external_file_archive_id' => $row->id,
                'error' => $e->getMessage(),
            ]);

            return $row->refresh();
        }

        $deleted = $disk->delete($localPath);
        if ($deleted) {
            $row->update(['local_deleted_at' => now()]);
            Log::channel('payments')->info('external_archive_local_deleted', [
                'external_file_archive_id' => $row->id,
                'original_local_path' => $localPath,
            ]);
        } else {
            Log::channel('payments')->warning('external_archive_local_delete_failed', [
                'external_file_archive_id' => $row->id,
                'original_local_path' => $localPath,
            ]);
        }

        $successLog = [
            'external_file_archive_id' => $row->id,
            'mega_path' => $upload->megaPath,
        ];
        if ($derivativeUpload !== null) {
            $successLog['original_bytes'] = $derivativeUpload->originalBytes;
            $successLog['archive_bytes'] = $derivativeUpload->archiveBytes;
            $successLog['reduction_percent'] = $derivativeUpload->reductionPercent();
        }
        Log::channel('payments')->info('external_archive_upload_succeeded', $successLog);

        return $row->refresh();
    }

    /**
     * Restore a previously uploaded archive from MEGA back to the original private path.
     */
    public function restoreFromMega(ExternalFileArchive $archive): void
    {
        if ($archive->status !== ExternalFileArchive::STATUS_UPLOADED) {
            throw new \InvalidArgumentException('Archive is not in uploaded state.');
        }
        $megaPath = $archive->mega_path;
        $generated = $archive->generated_file_name;
        if (($megaPath === null || $megaPath === '') && ($generated === null || $generated === '')) {
            throw new \InvalidArgumentException('Missing mega_path and generated_file_name on archive row.');
        }

        $disk = Storage::disk('local');
        $dest = $disk->path($archive->original_local_path);
        $dir = dirname($dest);
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $result = $this->megaClient->downloadToAbsolutePath(
            (string) ($megaPath ?? ''),
            $dest,
            $generated,
        );
        if (! $result->ok) {
            throw new \RuntimeException($result->error ?? 'MEGA download failed');
        }

        $archive->update(['local_deleted_at' => null]);
    }

    /**
     * Download from MEGA to original path for a short-lived admin preview.
     * Does not clear local_deleted_at (row still represents “canonical copy on MEGA”).
     */
    public function restoreFromMegaForPreview(ExternalFileArchive $archive): void
    {
        if ($archive->status !== ExternalFileArchive::STATUS_UPLOADED) {
            throw new \InvalidArgumentException('Archive is not in uploaded state.');
        }
        if ($archive->archive_provider !== ExternalFileArchive::PROVIDER_MEGA) {
            throw new \InvalidArgumentException('Unsupported archive provider.');
        }
        $megaPath = $archive->mega_path;
        $generated = $archive->generated_file_name;
        if (($megaPath === null || $megaPath === '') && ($generated === null || $generated === '')) {
            throw new \InvalidArgumentException('Missing mega_path and generated_file_name on archive row.');
        }

        $disk = Storage::disk('local');
        $dest = $disk->path($archive->original_local_path);
        $dir = dirname($dest);
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $result = $this->megaClient->downloadToAbsolutePath(
            (string) ($megaPath ?? ''),
            $dest,
            $generated,
        );
        if (! $result->ok) {
            throw new \RuntimeException($result->error ?? 'MEGA download failed');
        }

        $ttl = max(1, (int) config('external_archive.preview_ttl_minutes', 60));
        $expires = now()->addMinutes($ttl);
        $archive->update([
            'preview_restored_at' => now(),
            'preview_expires_at' => $expires,
        ]);

        Log::channel('payments')->info('external_archive_preview_restored', [
            'external_file_archive_id' => $archive->id,
            'original_local_path' => $archive->original_local_path,
            'preview_expires_at' => $expires->toIso8601String(),
        ]);
    }

    /**
     * Ensure a private file exists locally for an authorized preview.
     * Uses MEGA only when the file is missing and an uploaded archive row exists.
     */
    public function ensureLocalPreviewForSource(
        string $sourceTable,
        int $sourceId,
        ?string $sourceColumn,
        string $knownRelativePath,
    ): ?TemporaryPreviewFile {
        $disk = Storage::disk('local');
        if ($knownRelativePath === '' || str_contains($knownRelativePath, '..')) {
            return null;
        }

        if ($disk->exists($knownRelativePath)) {
            return new TemporaryPreviewFile($knownRelativePath, false);
        }

        $archive = ExternalFileArchive::query()
            ->where('source_table', $sourceTable)
            ->where('source_id', $sourceId)
            ->where('source_column', $sourceColumn)
            ->where('status', ExternalFileArchive::STATUS_UPLOADED)
            ->where('archive_provider', ExternalFileArchive::PROVIDER_MEGA)
            ->orderByDesc('id')
            ->first();

        if (! $archive instanceof ExternalFileArchive) {
            return null;
        }

        if ($archive->original_local_path !== $knownRelativePath) {
            return null;
        }

        if ($archive->local_deleted_at === null) {
            return null;
        }

        try {
            $this->restoreFromMegaForPreview($archive);
        } catch (Throwable $e) {
            Log::channel('payments')->warning('external_archive_preview_restore_failed', [
                'external_file_archive_id' => $archive->id,
                'source_table' => $sourceTable,
                'source_id' => $sourceId,
                'source_column' => $sourceColumn,
                'original_local_path' => $knownRelativePath,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $disk->exists($knownRelativePath)) {
            return null;
        }

        return new TemporaryPreviewFile($knownRelativePath, true);
    }
}
