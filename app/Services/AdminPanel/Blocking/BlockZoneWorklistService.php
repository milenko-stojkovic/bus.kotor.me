<?php

namespace App\Services\AdminPanel\Blocking;

use App\Models\BlockZoneWorklist;
use App\Models\DailyParkingData;
use App\Models\Reservation;
use App\Models\TempData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BlockZoneWorklistService
{
    /**
     * Kada pending pokušaj postane rezervacija: pending_payment → ready_to_adjust.
     */
    public function onReservationCreated(Reservation $reservation, TempData $temp): void
    {
        $row = BlockZoneWorklist::query()
            ->where('merchant_transaction_id', $reservation->merchant_transaction_id)
            ->first();
        if (! $row) {
            return;
        }

        if ($row->status === BlockZoneWorklist::STATUS_PENDING_PAYMENT) {
            $row->status = BlockZoneWorklist::STATUS_READY_TO_ADJUST;
        }
        $row->reservation_id = $reservation->id;
        $row->temp_data_id = $temp->id;
        $row->snapshot_json = array_merge((array) ($row->snapshot_json ?? []), [
            'user_name' => $reservation->user_name,
            'email' => $reservation->email,
            'reservation_id' => $reservation->id,
            'status' => $reservation->status,
        ]);
        $row->save();
    }

    /**
     * Kada pending pokušaj propadne/istekne: izbaci iz worklist i blokiraj ciljne slotove (ako su sada slobodni).
     */
    public function onTempDataFailedOrExpired(TempData $temp, string $reason): void
    {
        $row = BlockZoneWorklist::query()
            ->where('merchant_transaction_id', $temp->merchant_transaction_id)
            ->first();
        if (! $row) {
            return;
        }

        $targetSlots = (array) (($row->snapshot_json['target_block_slots'] ?? null) ?? []);
        $row->delete();

        if ($targetSlots === []) {
            return;
        }

        DB::transaction(function () use ($targetSlots, $temp, $reason): void {
            foreach ($targetSlots as $slotId) {
                $slotId = (int) $slotId;
                if ($slotId < 1) {
                    continue;
                }
                /** @var DailyParkingData|null $daily */
                $daily = DailyParkingData::query()
                    ->where('date', $temp->reservation_date)
                    ->where('time_slot_id', $slotId)
                    ->lockForUpdate()
                    ->first();
                if (! $daily) {
                    continue;
                }
                if ($daily->reserved > 0 || $daily->pending > 0) {
                    continue;
                }
                $daily->is_blocked = true;
                $daily->save();
            }
        });

        Log::channel('payments')->info('block_zone_pending_removed', [
            'merchant_transaction_id' => $temp->merchant_transaction_id,
            'temp_data_id' => $temp->id,
            'reason' => $reason,
        ]);
    }
}

