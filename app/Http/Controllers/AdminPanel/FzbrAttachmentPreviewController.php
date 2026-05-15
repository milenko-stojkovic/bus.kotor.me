<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use App\Models\FreeReservationRequest;
use App\Models\FreeReservationRequestAttachment;
use App\Services\AdminPanel\FreeReservation\ServeFzbrAttachmentFile;
use App\Services\ExternalArchive\ExternalFileArchiveService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Authenticated admin preview for FZBR attachments on terminal requests (fulfilled / rejected).
 */
final class FzbrAttachmentPreviewController extends Controller
{
    public function __invoke(
        FreeReservationRequestAttachment $freeReservationRequestAttachment,
        ExternalFileArchiveService $archiveService,
        ServeFzbrAttachmentFile $serve,
    ): BinaryFileResponse {
        $freeReservationRequestAttachment->loadMissing('request');
        $req = $freeReservationRequestAttachment->request;
        if ($req === null) {
            abort(404);
        }

        if (! in_array($req->status, [
            FreeReservationRequest::STATUS_FULFILLED,
            FreeReservationRequest::STATUS_REJECTED,
        ], true)) {
            abort(404);
        }

        return $serve->respond($freeReservationRequestAttachment, $archiveService);
    }
}
