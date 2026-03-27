<?php

namespace App\Services\Payment;

use App\Models\Reservation;
use App\Models\TempData;
use App\Support\UiText;

/**
 * Jedini izvor istine za status plaćanja: baza (reservation + temp_data).
 * UI uvek čita status preko ovog resolvera; nikad iz URL parametara ili frontend state-a.
 * Kad korisnik zatvori tab tokom redirecta i vrati se kasnije – stranica ponovo pita bazu.
 */
class PaymentResultResolver
{
    public const MESSAGE_FAILED_FALLBACK = 'Plaćanje nije uspelo. Vaši podaci su sačuvani – pokušajte ponovo.';

    /**
     * Vraća status za merchant_transaction_id iz baze.
     *
     * @return array{status: 'success'|'failed'|'pending'|'late_success', user_type: 'guest'|'auth', message?: string, reservation_id?: int, redirect_guest: string, redirect_auth: string}|null null ako tx ne postoji
     */
    public function resolve(string $merchantTransactionId): ?array
    {
        $locale = app()->getLocale();

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
                    'message' => UiText::t('errors', 'payment_failed', self::MESSAGE_FAILED_FALLBACK, $locale),
                    'retry_token' => $temp->retry_token,
                    'error_reason' => $temp->callback_error_reason,
                    'redirect_guest' => $temp->retry_token
                        ? route('reservations.create', ['retry_token' => $temp->retry_token])
                        : route('reservations.create'),
                    'redirect_auth' => $redirect['redirect_auth'],
                ];
            }

            if ($temp->status === TempData::STATUS_LATE_SUCCESS) {
                return [
                    'status' => 'late_success',
                    'user_type' => $userType,
                    'message' => __('Payment was confirmed after the reservation window closed. Please contact support.'),
                    'redirect_guest' => $redirect['redirect_guest'],
                    'redirect_auth' => $redirect['redirect_auth'],
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
