<?php

namespace App\Services\Payment;

use App\Jobs\ProcessReservationAfterPaymentJob;
use App\Models\DailyParkingData;
use App\Models\Reservation;
use App\Models\TempData;
use Illuminate\Support\Facades\DB;

/**
 * Zajednički flow za SUCCESS plaćanja: callback ili status inquiry (timeout callback).
 * Jedna transakcija: lock temp_data, provera rezervacije, kreiranje rezervacije, temp → processed, release soft-lock, dispatch ProcessReservationAfterPaymentJob.
 * Ako lock nije validan → prelaz u late_success (bez kreiranja rezervacije).
 */
class PaymentSuccessHandler
{
    /**
     * Primeni SUCCESS: kreira rezervaciju, temp_data → processed, oslobodi lock, dispatch posle plaćanja.
     * Ako lock nije validan, prelazi u late_success i vraća false.
     *
     * @return bool true ako je rezervacija kreirana, false inače (late_success ili već postoji)
     */
    public function handle(TempData $temp, array $rawPayload): bool
    {
        if (! $temp->isLockValidForProcessed()) {
            $this->transitionToLateSuccess($temp, false, $rawPayload);

            return false;
        }

        $created = false;
        DB::transaction(function () use ($temp, $rawPayload, &$created): void {
            $temp = TempData::where('merchant_transaction_id', $temp->merchant_transaction_id)->lockForUpdate()->first();
            if (! $temp || Reservation::where('merchant_transaction_id', $temp->merchant_transaction_id)->exists()) {
                return;
            }
            if (! $temp->isLockValidForProcessed()) {
                $from = $temp->status;
                $temp->update([
                    'status' => TempData::STATUS_LATE_SUCCESS,
                    'raw_callback_payload' => $rawPayload,
                ]);
                TempData::logStateTransition($temp->merchant_transaction_id, $from, TempData::STATUS_LATE_SUCCESS, 'SUCCESS but lock no longer valid');

                return;
            }

            $reservation = $this->createReservationFromTempData($temp);
            $from = $temp->status;
            $temp->update([
                'status' => TempData::STATUS_PROCESSED,
                'raw_callback_payload' => $rawPayload,
            ]);
            TempData::logStateTransition($temp->merchant_transaction_id, $from, TempData::STATUS_PROCESSED, 'SUCCESS');
            $this->doReleaseSoftLock($temp, true);
            ProcessReservationAfterPaymentJob::dispatch($reservation->id);
            $created = true;
        });

        return $created;
    }

    /**
     * Prelaz u late_success (callback timeout ili SUCCESS ali lock nije validan). Koristi PaymentCallbackJob za status 'timeout'.
     */
    public function applyLateSuccess(TempData $temp, array $rawPayload, bool $releaseLock): void
    {
        $this->transitionToLateSuccess($temp, $releaseLock, $rawPayload);
    }

    private function transitionToLateSuccess(TempData $temp, bool $releaseLock, array $rawPayload): void
    {
        DB::transaction(function () use ($temp, $releaseLock, $rawPayload): void {
            $temp = TempData::where('merchant_transaction_id', $temp->merchant_transaction_id)->lockForUpdate()->first();
            if (! $temp || $temp->status === TempData::STATUS_LATE_SUCCESS) {
                return;
            }
            $from = $temp->status;
            $temp->update([
                'status' => TempData::STATUS_LATE_SUCCESS,
                'raw_callback_payload' => $rawPayload,
            ]);
            TempData::logStateTransition($temp->merchant_transaction_id, $from, TempData::STATUS_LATE_SUCCESS, 'SUCCESS but lock expired/canceled or timeout');
            if ($releaseLock) {
                $this->doReleaseSoftLock($temp, false);
            }
        });
    }

    /** Javno za PaymentCallbackJob handleCanceled (oslobađanje lock-a bez increment reserved). */
    public function releaseSoftLock(TempData $temp, bool $incrementReserved): void
    {
        $this->doReleaseSoftLock($temp, $incrementReserved);
    }

    private function doReleaseSoftLock(TempData $temp, bool $incrementReserved): void
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
            'preferred_locale' => $temp->preferred_locale,
            'status' => 'paid',
            'email_sent' => 0,
        ]);
    }
}
