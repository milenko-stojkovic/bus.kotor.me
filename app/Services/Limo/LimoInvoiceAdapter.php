<?php

namespace App\Services\Limo;

use App\Models\LimoPickupEvent;
use Carbon\Carbon;

/**
 * Mapira LimoPickupEvent u „rezervaciji sličan” objekat za {@see \App\Services\FiscalizationService}
 * i {@see \App\Services\Pdf\PaidInvoicePdfGenerator::renderLimoBinary}.
 */
final class LimoInvoiceAdapter
{
    /**
     * Polja kompatibilna sa postojećim fiscal payload / PDF očekivanjima (Reservation-like).
     */
    public static function fromPickupEvent(LimoPickupEvent $event): object
    {
        $occurred = $event->occurred_at ?? now();
        if (! $occurred instanceof Carbon) {
            $occurred = Carbon::parse($occurred);
        }

        $slotNull = (object) ['time_slot' => null];

        return (object) [
            'id' => $event->id,
            'merchant_transaction_id' => $event->merchant_transaction_id,
            'user_name' => $event->agency_name_snapshot,
            'email' => $event->agency_email_snapshot,
            'country' => $event->agency_country_snapshot,
            'license_plate' => $event->license_plate_snapshot,
            'invoice_amount' => (float) $event->amount_snapshot,
            'preferred_locale' => 'cg',
            'created_at' => $occurred,
            'reservation_date' => $occurred->clone()->startOfDay(),
            'vehicleLine' => $event->service_name_snapshot ?: 'Naknada',
            'drop_off_time_slot' => null,
            'pick_up_time_slot' => null,
            'dropOffTimeSlot' => $slotNull,
            'pickUpTimeSlot' => $slotNull,
            'fiscal_jir' => $event->fiscal_jir,
            'fiscal_ikof' => $event->fiscal_ikof,
            'fiscal_qr' => $event->fiscal_qr,
        ];
    }
}
