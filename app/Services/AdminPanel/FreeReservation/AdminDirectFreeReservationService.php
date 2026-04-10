<?php

namespace App\Services\AdminPanel\FreeReservation;

use App\Exceptions\AdminFreeReservationSlotsUnavailableException;
use App\Jobs\SendFreeReservationConfirmationJob;
use App\Models\DailyParkingData;
use App\Models\Reservation;
use App\Services\AdminPanel\Blocking\BlockZoneWorklistService;
use App\Support\ReservationInvoiceAmount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Direktno kreiranje besplatne rezervacije iz admin panela (bez temp_data i plaćanja).
 * Isti uslovi kapaciteta/blokade kao checkout soft-lock korak, ali odmah increment reserved.
 */
final class AdminDirectFreeReservationService
{
    public function __construct(
        private BlockZoneWorklistService $blockZoneWorklistService,
    ) {}

    /**
     * @param  array{
     *     reservation_date: string,
     *     drop_off_time_slot_id: int,
     *     pick_up_time_slot_id: int,
     *     name: string,
     *     country: string,
     *     license_plate: string,
     *     vehicle_type_id: int,
     *     email: string
     * }  $data
     *
     * @throws AdminFreeReservationSlotsUnavailableException
     */
    public function create(array $data): Reservation
    {
        $date = (string) $data['reservation_date'];
        $drop = (int) $data['drop_off_time_slot_id'];
        $pick = (int) $data['pick_up_time_slot_id'];
        $slotIds = array_values(array_unique([$drop, $pick]));
        sort($slotIds);

        $merchantTransactionId = Str::uuid()->toString();

        $reservation = DB::transaction(function () use ($data, $date, $drop, $pick, $slotIds, $merchantTransactionId): Reservation {
            /** @var array<int, DailyParkingData> $locked */
            $locked = [];
            foreach ($slotIds as $slotId) {
                $row = DailyParkingData::query()
                    ->whereDate('date', $date)
                    ->where('time_slot_id', $slotId)
                    ->lockForUpdate()
                    ->first();
                if ($row === null || $row->is_blocked || $row->availableCapacity() < 1) {
                    throw new AdminFreeReservationSlotsUnavailableException;
                }
                $locked[$slotId] = $row;
            }

            $created = Reservation::query()->create([
                'user_id' => null,
                'vehicle_id' => null,
                'merchant_transaction_id' => $merchantTransactionId,
                'drop_off_time_slot_id' => $drop,
                'pick_up_time_slot_id' => $pick,
                'reservation_date' => $date,
                'user_name' => $data['name'],
                'country' => $data['country'],
                'license_plate' => $data['license_plate'],
                'vehicle_type_id' => (int) $data['vehicle_type_id'],
                'email' => $data['email'],
                'preferred_locale' => 'cg',
                'status' => 'free',
                'invoice_amount' => ReservationInvoiceAmount::snapshotForNewReservation('free', (int) $data['vehicle_type_id']),
                'email_sent' => Reservation::EMAIL_NOT_SENT,
                'created_by_admin' => true,
            ]);

            foreach ($slotIds as $slotId) {
                $locked[$slotId]->increment('reserved');
            }

            $this->blockZoneWorklistService->onReservationCreated($created, null);

            Log::channel('payments')->info('admin_direct_free_reservation_created', [
                'reservation_id' => $created->id,
                'merchant_transaction_id' => $created->merchant_transaction_id,
            ]);

            return $created;
        });

        SendFreeReservationConfirmationJob::dispatch($reservation->id);

        return $reservation;
    }
}
