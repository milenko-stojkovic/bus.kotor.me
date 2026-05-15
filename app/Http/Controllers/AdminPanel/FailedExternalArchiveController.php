<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use App\Models\ExternalFileArchive;
use App\Models\LimoPlateUpload;
use App\Services\ExternalArchive\ArchiveDerivativeUpload;
use App\Services\ExternalArchive\ExternalFileArchiveService;
use App\Services\Limo\LimoPlateArchiveDerivativeBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class FailedExternalArchiveController extends Controller
{
    public function index(): View
    {
        $failed = ExternalFileArchive::query()
            ->where('status', ExternalFileArchive::STATUS_FAILED)
            ->orderByDesc('updated_at')
            ->get();

        $disk = Storage::disk('local');
        $rows = $failed->map(function (ExternalFileArchive $row) use ($disk): object {
            $path = (string) $row->original_local_path;
            $pathOk = $path !== '' && ! str_contains($path, '..');
            $exists = $pathOk && $disk->exists($path);

            return (object) [
                'archive' => $row,
                'local_file_exists' => $exists,
            ];
        });

        return view('admin-panel.failed-external-archives', [
            'rows' => $rows,
        ]);
    }

    public function retry(
        ExternalFileArchive $externalFileArchive,
        ExternalFileArchiveService $archiveService,
        LimoPlateArchiveDerivativeBuilder $plateDerivativeBuilder,
    ): RedirectResponse {
        if ($externalFileArchive->status !== ExternalFileArchive::STATUS_FAILED) {
            abort(404);
        }

        $derivativeUpload = null;

        try {
            if ($externalFileArchive->archived_derivative) {
                $derivativeUpload = $this->buildDerivativeUploadForRetry($externalFileArchive, $plateDerivativeBuilder);
            }

            $archiveService->retryFailedArchive($externalFileArchive, $derivativeUpload);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('panel_admin.archive.failed')->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('panel_admin.archive.failed')->with('error', 'Neočekivana greška pri ponovnom pokušaju.');
        }

        $externalFileArchive->refresh();

        if ($externalFileArchive->status === ExternalFileArchive::STATUS_UPLOADED) {
            return redirect()->route('panel_admin.archive.failed')->with('status', 'Arhiva je uspješno završena za red #'.$externalFileArchive->id.'.');
        }

        return redirect()->route('panel_admin.archive.failed')->with('error', 'Ponovni pokušaj nije uspio: '.(string) ($externalFileArchive->error_message ?? 'nepoznato'));
    }

    private function buildDerivativeUploadForRetry(
        ExternalFileArchive $row,
        LimoPlateArchiveDerivativeBuilder $builder,
    ): ArchiveDerivativeUpload {
        if ($row->context_type !== 'limo_plate_upload') {
            throw new InvalidArgumentException('Automatski derivat je podržan samo za limo_plate_upload.');
        }

        $plate = LimoPlateUpload::query()->find($row->source_id);
        if (! $plate instanceof LimoPlateUpload) {
            throw new InvalidArgumentException('Izvorni Limo plate upload nije pronađen.');
        }

        $localPath = (string) $row->original_local_path;
        $disk = Storage::disk('local');
        $absolute = $disk->path($localPath);

        $built = $builder->buildForArchive($absolute, $plate->plateCropBasisPoints());
        if ($built === null) {
            throw new InvalidArgumentException('Nije moguće ponovo pripremiti JPEG derivat (GD / fajl).');
        }

        return new ArchiveDerivativeUpload(
            uploadAbsolutePath: $built->absolutePath,
            derivativeSourcePath: $localPath,
            derivativeOptions: $built->options,
            originalBytes: $built->originalBytes,
            archiveBytes: $built->archiveBytes,
            generatedExtension: 'jpg',
        );
    }
}
