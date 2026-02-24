<?php

namespace App\Jobs;

use App\Events\PaymentFailed;
use App\Models\DailyParkingData;
use App\Models\Reservation;
use App\Models\TempData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Obrada rezultata plaćanja iz webhook/callback. Idempotentan po merchant_transaction_id.
 *
 * Pravila:
 * - Ako je temp_data.status već failed ili processed → prekinuti (return).
 * - Nikad ne brisati temp_data fizički (audit trail); samo menjati status.
 * - SUCCESS → kreiraj reservation, temp_data.status = processed, oslobodi soft-lock (pending→reserved), PostFiscalizationJob.
 * - CANCEL/ERROR/failed → temp_data.status = failed, sačuvaj raw payload + error, NE kreiraj reservation, oslobodi soft-lock, dispatch PaymentFailed.
 */
class PaymentCallbackJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        /** Validirani payload: merchant_transaction_id, status (success|failed|timeout|CANCEL|ERROR), error_code?, error_reason? */
        public array $payload,
        /** Raw callback payload za audit (raw_callback_payload). */
        public array $rawPayload = []
    ) {}

    public function handle(): void
    {
        $txId = $this->payload['merchant_transaction_id'] ?? null;
        $status = $this->normalizeStatus($this->payload['status'] ?? null);

        if (! $txId || ! $status) {
            return;
        }

        $temp = TempData::where('merchant_transaction_id', $txId)->first();
        if (! $temp) {
            return;
        }

        if ($temp->isFinalStatus()) {
            return;
        }

        if (Reservation::where('merchant_transaction_id', $txId)->exists()) {
            return;
        }

        if ($status === 'success') {
            $this->handleSuccess($temp);
            return;
        }

        if ($status === 'timeout') {
            $temp->update(['status' => TempData::STATUS_LATE_SUCCESS]);
            $this->releaseSoftLock($temp, false);
            return;
        }

        $this->handleFailed($temp);
    }

    private function normalizeStatus(?string $status): ?string
    {
        if ($status === null) {
            return null;
        }
        if (in_array($status, ['CANCEL', 'ERROR'], true)) {
            return 'failed';
        }

        return in_array($status, ['success', 'failed', 'timeout'], true) ? $status : null;
    }

    private function handleSuccess(TempData $temp): void
    {
        $reservation = $this->createReservationFromTempData($temp);
        $temp->update([
            'status' => TempData::STATUS_PROCESSED,
            'raw_callback_payload' => $this->rawPayload,
        ]);
        $this->releaseSoftLock($temp, true);
        PostFiscalizationJob::dispatch($reservation->id);
    }

    private function handleFailed(TempData $temp): void
    {
        $temp->update([
            'status' => TempData::STATUS_FAILED,
            'raw_callback_payload' => $this->rawPayload,
            'callback_error_code' => $this->payload['error_code'] ?? null,
            'callback_error_reason' => $this->payload['error_reason'] ?? null,
        ]);
        $this->releaseSoftLock($temp, false);
        event(new PaymentFailed($temp));
    }

    private function releaseSoftLock(TempData $temp, bool $incrementReserved): void
    {
        $daily = DailyParkingData::where('date', $temp->reservation_date)
            ->where('time_slot_id', $temp->drop_off_time_slot_id)
            ->first();

        if (! $daily) {
            return;
        }

        $daily->decrement('pending');
        if ($incrementReserved) {
            $daily->increment('reserved');
        }
    }

    public function uniqueId(): string
    {
        return (string) ($this->payload['merchant_transaction_id'] ?? $this->job?->getJobId() ?? 'payment-callback');
    }

    private function createReservationFromTempData(TempData $temp): Reservation
    {
        return Reservation::create([
            'user_id' => $temp->user_id,
            'vehicle_id' => null,
            'merchant_transaction_id' => $temp->merchant_transaction_id,
            'drop_off_time_slot_id' => $temp->drop_off_time_slot_id,
            'pick_up_time_slot_id' => $temp->pick_up_time_slot_id,
            'reservation_date' => $temp->reservation_date,
            'user_name' => $temp->user_name,
            'country' => $temp->country,
            'license_plate' => $temp->license_plate,
            'vehicle_type_id' => $temp->vehicle_type_id,
            'email' => $temp->email,
            'status' => 'paid',
            'email_sent' => 0,
        ]);
    }
}
