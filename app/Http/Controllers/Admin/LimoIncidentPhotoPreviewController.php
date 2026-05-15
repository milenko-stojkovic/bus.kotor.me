<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LimoIncident;
use App\Services\ExternalArchive\ExternalFileArchiveService;
use App\Support\LimoIncidentEvidencePreviewPath;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * On-demand incident plate/branding images for admin (local or temporary MEGA re-download).
 */
final class LimoIncidentPhotoPreviewController extends Controller
{
    public function plate(LimoIncident $limoIncident, ExternalFileArchiveService $archiveService): Response
    {
        return $this->serve($limoIncident, 'plate_photo_path', $archiveService);
    }

    public function branding(LimoIncident $limoIncident, ExternalFileArchiveService $archiveService): Response
    {
        return $this->serve($limoIncident, 'branding_photo_path', $archiveService);
    }

    private function serve(LimoIncident $limoIncident, string $column, ExternalFileArchiveService $archiveService): Response
    {
        $relative = (string) ($limoIncident->{$column} ?? '');
        if ($relative === '') {
            abort(404);
        }

        if (! LimoIncidentEvidencePreviewPath::isAllowedRelativePath($relative)) {
            abort(404);
        }

        $preview = $archiveService->ensureLocalPreviewForSource(
            $limoIncident->getTable(),
            (int) $limoIncident->id,
            $column,
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

        $mime = @mime_content_type($absolute) ?: 'application/octet-stream';

        return response()->file($absolute, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=60',
        ]);
    }
}
