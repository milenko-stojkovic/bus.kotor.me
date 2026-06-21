<?php

namespace App\Services\VehicleCategoryChange;

use App\Contracts\MegaArchiveClient;
use App\Models\VehicleCategoryChangeRequest;
use App\Models\VehicleCategoryChangeRequestAttachment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

final class VehicleCategoryChangeAttachmentArchiveService
{
    public const PROVIDER_MEGA = 'mega';

    public const ERROR_LOCAL_FILE_MISSING = 'local_file_missing';

    public function __construct(
        private readonly MegaArchiveClient $megaClient,
    ) {}

    public function archiveRequestAttachments(VehicleCategoryChangeRequest $request): void
    {
        if (! in_array($request->status, [
            VehicleCategoryChangeRequest::STATUS_APPROVED,
            VehicleCategoryChangeRequest::STATUS_REJECTED,
        ], true)) {
            return;
        }

        $request->loadMissing('attachments');

        foreach ($request->attachments as $attachment) {
            $this->archiveAttachment($request, $attachment);
        }
    }

    public function archiveAttachment(
        VehicleCategoryChangeRequest $request,
        VehicleCategoryChangeRequestAttachment $attachment,
    ): void {
        if ($this->isFullyArchived($attachment)) {
            $this->deleteLocalFileIfStillPresent($attachment);

            return;
        }

        if ($attachment->archived_at !== null && $attachment->archive_path) {
            $this->deleteLocalFileIfStillPresent($attachment);

            return;
        }

        $disk = $attachment->disk ?: 'local';
        $localPath = (string) $attachment->path;

        if ($localPath === '' || ! Storage::disk($disk)->exists($localPath)) {
            $attachment->update([
                'archive_error' => self::ERROR_LOCAL_FILE_MISSING,
            ]);
            Log::channel('payments')->warning('vehicle_category_change_attachment_archive_local_missing', [
                'request_id' => (int) $request->id,
                'attachment_id' => (int) $attachment->id,
                'path' => $localPath !== '' ? $localPath : null,
            ]);

            return;
        }

        $relativeDir = $this->buildMegaRelativeDirectory($request);
        $targetName = $this->buildMegaFileName($attachment);
        $absolutePath = Storage::disk($disk)->path($localPath);

        $result = $this->megaClient->uploadLocalFileToRelativePath($absolutePath, $relativeDir, $targetName);

        if (! $result->ok || $result->megaPath === null || $result->megaPath === '') {
            $attachment->update([
                'archive_error' => $result->error ?? 'mega_upload_failed',
            ]);
            Log::channel('payments')->warning('vehicle_category_change_attachment_archive_failed', [
                'request_id' => (int) $request->id,
                'attachment_id' => (int) $attachment->id,
                'error' => $result->error,
            ]);

            return;
        }

        $attachment->update([
            'archived_at' => now(),
            'archive_provider' => self::PROVIDER_MEGA,
            'archive_path' => $result->megaPath,
            'archive_error' => null,
        ]);

        $this->deleteLocalFileIfStillPresent($attachment->fresh());

        Log::channel('payments')->info('vehicle_category_change_attachment_archived', [
            'request_id' => (int) $request->id,
            'attachment_id' => (int) $attachment->id,
            'archive_path' => $result->megaPath,
        ]);
    }

    public function buildMegaRelativeDirectory(VehicleCategoryChangeRequest $request): string
    {
        $status = (string) $request->status;
        $date = $request->reviewed_at ?? now();

        return sprintf(
            'vehicle-category-changes/%s/%s/%s/request-%d',
            $status,
            $date->format('Y'),
            $date->format('m'),
            (int) $request->id,
        );
    }

    public function buildMegaFileName(VehicleCategoryChangeRequestAttachment $attachment): string
    {
        $base = (string) ($attachment->original_name ?: 'document');
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $base) ?: 'document';
        $safe = trim($safe, '-.');

        return 'attachment-'.$attachment->id.'-'.$safe;
    }

    private function isFullyArchived(VehicleCategoryChangeRequestAttachment $attachment): bool
    {
        return $attachment->archived_at !== null
            && $attachment->archive_path
            && $attachment->local_deleted_at !== null;
    }

    private function deleteLocalFileIfStillPresent(?VehicleCategoryChangeRequestAttachment $attachment): void
    {
        if ($attachment === null) {
            return;
        }

        $disk = $attachment->disk ?: 'local';
        $localPath = (string) $attachment->path;

        if ($localPath !== '' && Storage::disk($disk)->exists($localPath)) {
            Storage::disk($disk)->delete($localPath);
        }

        if ($attachment->local_deleted_at === null) {
            $attachment->update(['local_deleted_at' => now()]);
        }
    }
}
