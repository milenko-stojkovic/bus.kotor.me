<?php

namespace App\Jobs;

use App\Models\VehicleCategoryChangeRequest;
use App\Services\VehicleCategoryChange\VehicleCategoryChangeAttachmentArchiveService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

final class ArchiveVehicleCategoryChangeRequestAttachmentsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $vehicleCategoryChangeRequestId,
    ) {}

    public function handle(VehicleCategoryChangeAttachmentArchiveService $archiveService): void
    {
        $request = VehicleCategoryChangeRequest::query()
            ->with('attachments')
            ->find($this->vehicleCategoryChangeRequestId);

        if ($request === null) {
            return;
        }

        if ($request->status === VehicleCategoryChangeRequest::STATUS_PENDING) {
            return;
        }

        $archiveService->archiveRequestAttachments($request);
    }

    public function failed(\Throwable $exception): void
    {
        Log::channel('payments')->error('vehicle_category_change_attachment_archive_job_failed', [
            'request_id' => $this->vehicleCategoryChangeRequestId,
            'error' => $exception->getMessage(),
        ]);
    }
}
