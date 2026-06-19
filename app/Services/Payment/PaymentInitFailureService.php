<?php

namespace App\Services\Payment;

use App\Models\TempData;
use App\Services\AdminPanel\Blocking\BlockZoneWorklistService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * When Bankart createSession fails before redirect, temp_data must not stay pending (blocks retry + holds capacity).
 */
class PaymentInitFailureService
{
    public const RESOLUTION_REASON = 'payment_init_failed';

    public function failAndRelease(
        TempData $temp,
        string $stage,
        ?int $httpStatus = null,
        ?string $reason = null,
    ): void {
        $merchantTransactionId = $temp->merchant_transaction_id;
        $tempDataId = $temp->id;

        DB::transaction(function () use ($temp): void {
            $temp = TempData::where('id', $temp->id)->lockForUpdate()->first();
            if (! $temp || $temp->status !== TempData::STATUS_PENDING) {
                return;
            }

            $from = $temp->status;
            $temp->update([
                'status' => TempData::STATUS_CANCELED,
                'resolution_reason' => self::RESOLUTION_REASON,
            ]);
            TempData::logStateTransition(
                $temp->merchant_transaction_id,
                $from,
                TempData::STATUS_CANCELED,
                'createSession failed before bank redirect',
            );

            app(PaymentSuccessHandler::class)->releaseSoftLock($temp, false);
            app(BlockZoneWorklistService::class)->onTempDataFailedOrExpired($temp, 'canceled');
        });

        Log::channel('payments')->warning('payment_init_failed', [
            'stage' => $stage,
            'merchant_transaction_id' => $merchantTransactionId,
            'temp_data_id' => $tempDataId,
            'http_status' => $httpStatus,
            'reason' => $reason ?? 'unavailable',
        ]);
    }
}
