<?php

namespace App\Services\Payment;

use App\Models\Reservation;
use App\Models\TempData;

/**
 * Jedini izvor istine za status plaćanja: baza (reservation + temp_data).
 * UI uvek čita status preko ovog resolvera; nikad iz URL parametara ili frontend state-a.
 * Kad korisnik zatvori tab tokom redirecta i vrati se kasnije – stranica ponovo pita bazu.
 */
class PaymentResultResolver
{
    public const MESSAGE_FAILED = 'Plaćanje je otkazano ili nije uspelo. Rezervacija nije sačuvana.';

    /**
     * Vraća status za merchant_transaction_id iz baze.
     *
     * @return array{status: 'success'|'failed'|'pending', user_type: 'guest'|'auth', message?: string, reservation_id?: int, redirect_guest: string, redirect_auth: string}|null null ako tx ne postoji
     */
    public function resolve(string $merchantTransactionId): ?array
    {
        $reservation = Reservation::where('merchant_transaction_id', $merchantTransactionId)->first();
        if ($reservation) {
            return [
                'status' => 'success',
                'user_type' => $reservation->user_id ? 'auth' : 'guest',
                'reservation_id' => $reservation->id,
                'redirect_guest' => route('reservations.create'),
                'redirect_auth' => route('profile.reservations'),
            ];
        }

        $temp = TempData::where('merchant_transaction_id', $merchantTransactionId)->first();
        if ($temp) {
            $userType = $temp->user_id ? 'auth' : 'guest';
            $redirect = [
                'redirect_guest' => route('reservations.create'),
                'redirect_auth' => route('profile.reservations'),
            ];
            if (in_array($temp->status, [TempData::STATUS_CANCELED, TempData::STATUS_EXPIRED], true)) {
                return [
                    'status' => 'failed',
                    'user_type' => $userType,
                    'message' => self::MESSAGE_FAILED,
                    ...$redirect,
                ];
            }

            return [
                'status' => 'pending',
                'user_type' => $userType,
                ...$redirect,
            ];
        }

        return null;
    }
}
