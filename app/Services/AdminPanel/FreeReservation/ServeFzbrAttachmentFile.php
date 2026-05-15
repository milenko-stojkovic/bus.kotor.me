<?php

namespace App\Services\AdminPanel\FreeReservation;

use App\Models\FreeReservationRequestAttachment;
use App\Services\ExternalArchive\ExternalFileArchiveService;
use App\Support\FzbrAttachmentPreviewPath;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ServeFzbrAttachmentFile
{
    public function respond(
        FreeReservationRequestAttachment $attachment,
        ExternalFileArchiveService $archiveService,
    ): BinaryFileResponse {
        $relative = (string) $attachment->stored_path;
        if (! FzbrAttachmentPreviewPath::isAllowedRelativePath($relative)) {
            abort(404);
        }

        $preview = $archiveService->ensureLocalPreviewForSource(
            (new FreeReservationRequestAttachment)->getTable(),
            (int) $attachment->id,
            'stored_path',
            $relative,
        );
        if ($preview === null) {
            abort(404);
        }

        $disk = Storage::disk('local');
        $absolute = $disk->path($preview->relativePrivatePath);
        if (! is_file($absolute)) {
            abort(404);
        }

        $mime = (is_string($attachment->mime_type) && $attachment->mime_type !== '')
            ? (string) $attachment->mime_type
            : ((string) (@mime_content_type($absolute) ?: 'application/octet-stream'));

        $disposition = $this->shouldServeInline($mime) ? 'inline' : 'attachment';

        return response()->file($absolute, [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition.'; filename="'.addslashes((string) $attachment->original_name).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function shouldServeInline(string $mime): bool
    {
        return str_starts_with($mime, 'image/')
            || $mime === 'application/pdf';
    }
}
