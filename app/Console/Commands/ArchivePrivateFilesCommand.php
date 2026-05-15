<?php

namespace App\Console\Commands;

use App\Models\ExternalFileArchive;
use App\Models\FreeReservationRequest;
use App\Models\FreeReservationRequestAttachment;
use App\Models\LimoPickupPhoto;
use App\Models\LimoPlateUpload;
use App\Services\ExternalArchive\ArchiveDerivativeUpload;
use App\Services\ExternalArchive\ExternalFileArchiveService;
use App\Services\ExternalArchive\MegaDiagnoseService;
use App\Services\Limo\LimoPlateArchiveDerivativeBuilder;
use App\Support\OperationalHeartbeatCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Offloads eligible private-disk files to MEGA and deletes local copies after success.
 * Conservative: only terminal FZBR, consumed plate uploads, pickup photos after invoice email sent.
 * Does not handle limo_incidents yet (evidence / email flows) — see TODO in handle().
 */
class ArchivePrivateFilesCommand extends Command
{
    protected $signature = 'files:archive-private
                            {--source=all : all|fzbr|limo}
                            {--dry-run : List candidates; no upload or delete}
                            {--limit=100 : Max candidates per category (fzbr / limo plates / limo pickup photos)}
                            {--require-mega-health : Abort before archiving if MEGA diagnose is not healthy (scheduled runs)}';

    protected $description = 'Archive eligible private files to MEGA (server-side only)';

    public function handle(
        ExternalFileArchiveService $archiveService,
        LimoPlateArchiveDerivativeBuilder $plateDerivativeBuilder,
    ): int {
        $source = strtolower(trim((string) $this->option('source')));
        if (! in_array($source, ['all', 'fzbr', 'limo'], true)) {
            $this->error('Invalid --source (use all, fzbr, or limo).');

            return self::INVALID;
        }

        Cache::put(
            OperationalHeartbeatCache::ARCHIVE_PRIVATE_LAST_RUN_AT,
            now()->toIso8601String(),
            OperationalHeartbeatCache::ttl(),
        );

        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));
        $requireMegaHealth = (bool) $this->option('require-mega-health');

        if ($requireMegaHealth && ! $dryRun) {
            $gate = $this->megaArchiveHealthGate(app(MegaDiagnoseService::class));
            if (! $gate['ok']) {
                $this->warn('Skipped: MEGA not ready for archive ('.$gate['reason'].').');
                Log::channel('payments')->info('files_archive_private_skipped_mega_unhealthy', [
                    'reason' => $gate['reason'],
                    'source' => $source,
                    'limit' => $limit,
                    'mega_login_ok' => $gate['mega']['login_ok'] ?? null,
                    'mega_folder_found' => $gate['mega']['folder_found'] ?? null,
                    'mega_ok' => $gate['mega']['ok'] ?? null,
                    'mega_configured' => $gate['mega_configured'] ?? null,
                ]);
                $this->rememberArchivePrivateHeartbeatOk(
                    $source,
                    $limit,
                    $dryRun,
                    $requireMegaHealth,
                    0,
                    0,
                    0,
                    0,
                    $gate['reason'],
                );

                return self::SUCCESS;
            }
        }

        if ($dryRun) {
            $this->warn('DRY RUN: no uploads and no local deletes.');
        }

        $scanned = $archived = $failed = $skipped = 0;
        $disk = Storage::disk('local');

        $fzbrTable = (new FreeReservationRequestAttachment)->getTable();
        $plateTable = (new LimoPlateUpload)->getTable();
        $pickupPhotoTable = (new LimoPickupPhoto)->getTable();

        if ($source === 'all' || $source === 'fzbr') {
            $attachments = FreeReservationRequestAttachment::query()
                ->select($fzbrTable.'.*')
                ->join('free_reservation_requests', 'free_reservation_requests.id', '=', $fzbrTable.'.request_id')
                ->whereIn('free_reservation_requests.status', [
                    FreeReservationRequest::STATUS_FULFILLED,
                    FreeReservationRequest::STATUS_REJECTED,
                ])
                ->orderBy($fzbrTable.'.id')
                ->limit($limit)
                ->get();

            foreach ($attachments as $att) {
                $scanned++;
                $path = (string) $att->stored_path;
                $col = 'stored_path';
                if ($path === '') {
                    $skipped++;

                    continue;
                }
                if ($this->hasUploadedArchive($fzbrTable, (int) $att->id, $col)) {
                    $skipped++;

                    continue;
                }
                if (! $disk->exists($path)) {
                    $skipped++;

                    continue;
                }
                if ($dryRun) {
                    $archived++;

                    continue;
                }
                try {
                    $row = $archiveService->archiveLocalPrivateFile(
                        $fzbrTable,
                        (int) $att->id,
                        $col,
                        $path,
                        'fzbr_attachment',
                    );
                    $this->recordArchiveServiceResult($row, 'FZBR attachment '.$att->id, $archived, $failed);
                } catch (Throwable $e) {
                    $failed++;
                    $this->error('FZBR attachment '.$att->id.': '.$e->getMessage());
                }
            }
        }

        if ($source === 'all' || $source === 'limo') {
            // Plate uploads consumed (OCR / confirm / incident-from-upload flows set consumed_at when applicable).
            $plates = LimoPlateUpload::query()
                ->whereNotNull('consumed_at')
                ->whereNotNull('path')
                ->where('path', '!=', '')
                ->orderBy('id')
                ->limit($limit)
                ->get();

            foreach ($plates as $row) {
                $scanned++;
                $path = (string) $row->path;
                $col = 'path';
                if ($this->hasUploadedArchive($plateTable, (int) $row->id, $col)) {
                    $skipped++;

                    continue;
                }
                if (! $disk->exists($path)) {
                    $skipped++;

                    continue;
                }
                if ($dryRun) {
                    $archived++;

                    continue;
                }
                try {
                    $derivativeUpload = $this->buildLimoPlateDerivativeUpload(
                        $plateDerivativeBuilder,
                        $disk->path($path),
                        $path,
                        $row->plateCropBasisPoints(),
                    );
                    if ($derivativeUpload === null) {
                        $failed++;
                        $this->error('limo_plate_uploads '.$row->id.': failed to build archive derivative image');

                        continue;
                    }

                    $archiveRow = $archiveService->archiveLocalPrivateFile(
                        $plateTable,
                        (int) $row->id,
                        $col,
                        $path,
                        'limo_plate_upload',
                        $derivativeUpload,
                    );
                    $this->recordArchiveServiceResult($archiveRow, 'limo_plate_uploads '.$row->id, $archived, $failed);
                } catch (Throwable $e) {
                    $failed++;
                    $this->error('limo_plate_uploads '.$row->id.': '.$e->getMessage());
                }
            }

            // Pickup photos: only after invoice email is sent for the parent event (conservative).
            $photos = LimoPickupPhoto::query()
                ->select($pickupPhotoTable.'.*')
                ->join('limo_pickup_events', 'limo_pickup_events.id', '=', $pickupPhotoTable.'.limo_pickup_event_id')
                ->whereNotNull('limo_pickup_events.invoice_email_sent_at')
                ->whereNotNull($pickupPhotoTable.'.path')
                ->where($pickupPhotoTable.'.path', '!=', '')
                ->orderBy($pickupPhotoTable.'.id')
                ->limit($limit)
                ->get();

            foreach ($photos as $photo) {
                $scanned++;
                $path = (string) $photo->path;
                $col = 'path';
                if ($this->hasUploadedArchive($pickupPhotoTable, (int) $photo->id, $col)) {
                    $skipped++;

                    continue;
                }
                if (! $disk->exists($path)) {
                    $skipped++;

                    continue;
                }
                if ($dryRun) {
                    $archived++;

                    continue;
                }
                try {
                    $archiveRow = $archiveService->archiveLocalPrivateFile(
                        $pickupPhotoTable,
                        (int) $photo->id,
                        $col,
                        $path,
                        'limo_pickup_photo',
                    );
                    $this->recordArchiveServiceResult($archiveRow, 'limo_pickup_photos '.$photo->id, $archived, $failed);
                } catch (Throwable $e) {
                    $failed++;
                    $this->error('limo_pickup_photos '.$photo->id.': '.$e->getMessage());
                }
            }

            // TODO: limo_incidents private files — only after explicit policy (pending email / evidence retention).
        }

        $this->info('Scanned: '.$scanned);
        $this->info($dryRun ? 'Would archive: '.$archived : 'Archived: '.$archived);
        $this->info('Failed: '.$failed);
        $this->info('Skipped: '.$skipped);

        Log::channel('payments')->info('files_archive_private_summary', [
            'source' => $source,
            'limit' => $limit,
            'dry_run' => $dryRun,
            'require_mega_health' => $requireMegaHealth,
            'scanned' => $scanned,
            'archived' => $archived,
            'failed' => $failed,
            'skipped' => $skipped,
        ]);

        $this->rememberArchivePrivateHeartbeatOk(
            $source,
            $limit,
            $dryRun,
            $requireMegaHealth,
            $scanned,
            $archived,
            $failed,
            $skipped,
            null,
        );

        return self::SUCCESS;
    }

    /**
     * Cache heartbeat for future operational dashboard (see OperationalHeartbeatCache).
     *
     * @param  string|null  $abortedReason  e.g. mega gate reason when no archiving ran
     */
    private function rememberArchivePrivateHeartbeatOk(
        string $source,
        int $limit,
        bool $dryRun,
        bool $requireMegaHealth,
        int $scanned,
        int $archived,
        int $failed,
        int $skipped,
        ?string $abortedReason,
    ): void {
        Cache::put(
            OperationalHeartbeatCache::ARCHIVE_PRIVATE_LAST_OK_AT,
            now()->toIso8601String(),
            OperationalHeartbeatCache::ttl(),
        );

        $payload = [
            'scanned' => $scanned,
            'archived' => $archived,
            'failed' => $failed,
            'skipped' => $skipped,
            'timestamp' => now()->toIso8601String(),
            'source' => $source,
            'limit' => $limit,
            'dry_run' => $dryRun,
            'require_mega_health' => $requireMegaHealth,
        ];
        if ($abortedReason !== null && $abortedReason !== '') {
            $payload['aborted'] = $abortedReason;
        }

        Cache::put(
            OperationalHeartbeatCache::ARCHIVE_PRIVATE_LAST_SUMMARY,
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            OperationalHeartbeatCache::ttl(),
        );
    }

    /**
     * @param  MegaDiagnoseService|\Mockery\MockInterface  $megaDiagnose
     * @return array{ok: bool, reason: string, mega: array<string, mixed>, mega_configured?: bool}
     */
    private function megaArchiveHealthGate(mixed $megaDiagnose): array
    {
        $mega = $megaDiagnose->run();
        $megaConfigured = ($mega['email_present'] ?? false) && ($mega['password_present'] ?? false);
        if (! $megaConfigured) {
            return [
                'ok' => false,
                'reason' => 'mega_not_configured',
                'mega' => $mega,
                'mega_configured' => false,
            ];
        }

        $unhealthy = ! ($mega['login_ok'] ?? false)
            || ! ($mega['folder_found'] ?? false)
            || ! ($mega['ok'] ?? false);

        if ($unhealthy) {
            return [
                'ok' => false,
                'reason' => 'mega_diagnose_unhealthy',
                'mega' => $mega,
                'mega_configured' => true,
            ];
        }

        return [
            'ok' => true,
            'reason' => '',
            'mega' => $mega,
            'mega_configured' => true,
        ];
    }

    /**
     * @param  array{left:int,top:int,width:int,height:int}|null  $cropBasisPoints
     */
    private function buildLimoPlateDerivativeUpload(
        LimoPlateArchiveDerivativeBuilder $builder,
        string $absoluteOriginalPath,
        string $relativeOriginalPath,
        ?array $cropBasisPoints,
    ): ?ArchiveDerivativeUpload {
        $built = $builder->buildForArchive($absoluteOriginalPath, $cropBasisPoints);
        if ($built === null) {
            return null;
        }

        return new ArchiveDerivativeUpload(
            uploadAbsolutePath: $built->absolutePath,
            derivativeSourcePath: $relativeOriginalPath,
            derivativeOptions: $built->options,
            originalBytes: $built->originalBytes,
            archiveBytes: $built->archiveBytes,
            generatedExtension: 'jpg',
        );
    }

    private function hasUploadedArchive(string $sourceTable, int $sourceId, string $sourceColumn): bool
    {
        return ExternalFileArchive::query()
            ->where('source_table', $sourceTable)
            ->where('source_id', $sourceId)
            ->where('source_column', $sourceColumn)
            ->where('status', ExternalFileArchive::STATUS_UPLOADED)
            ->exists();
    }

    /**
     * Tally Archived / Failed from {@see ExternalFileArchiveService::archiveLocalPrivateFile} return value.
     * Upload failures update the row to {@see ExternalFileArchive::STATUS_FAILED} without throwing.
     */
    private function recordArchiveServiceResult(ExternalFileArchive $row, string $errorPrefix, int &$archived, int &$failed): void
    {
        if ($row->status === ExternalFileArchive::STATUS_UPLOADED) {
            $archived++;

            return;
        }

        $failed++;
        if ($row->status === ExternalFileArchive::STATUS_FAILED) {
            $this->error($errorPrefix.': '.(string) ($row->error_message ?? 'failed'));

            return;
        }

        $this->error($errorPrefix.': unexpected archive status '.(string) $row->status);
    }
}
