<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LimoPickupEvent;
use App\Models\LimoPickupPhoto;
use App\Services\ExternalArchive\ExternalFileArchiveService;
use App\Support\LimoPlateEvidencePreviewPath;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * On-demand plate evidence image for admin (local or temporary MEGA re-download).
 */
final class LimoPickupPlatePhotoPreviewController extends Controller
{
    public function __invoke(LimoPickupEvent $limoPickupEvent, ExternalFileArchiveService $archiveService): Response
    {
        if ($limoPickupEvent->source !== 'plate') {
            abort(404);
        }

        $photo = $limoPickupEvent->photos()->where('type', 'plate')->first();
        if ($photo === null || $photo->path === null || $photo->path === '') {
            abort(404);
        }

        $relative = (string) $photo->path;
        if (! LimoPlateEvidencePreviewPath::isAllowedRelativePath($relative)) {
            abort(404);
        }

        $table = (new LimoPickupPhoto)->getTable();
        $preview = $archiveService->ensureLocalPreviewForSource($table, (int) $photo->id, 'path', $relative);
        if ($preview === null) {
            abort(404);
        }

        $disk = Storage::disk('local');
        $absolute = $disk->path($preview->relativePrivatePath);
        if (! is_file($absolute)) {
            abort(404);
        }

        $mime = @mime_content_type($absolute) ?: 'application/octet-stream';

        return response()->file($absolute, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=60',
        ]);
    }
}
