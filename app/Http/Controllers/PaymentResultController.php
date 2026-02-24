<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\TempData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Frontend success/cancel URL ping-a ovaj endpoint da dobije status + user_type za redirect.
 * Backend vraća JSON: { status, user_type, message? }. Frontend radi redirect na odgovarajuću stranicu.
 */
class PaymentResultController extends Controller
{
    public const MESSAGE_FAILED = 'Plaćanje je otkazano ili nije uspelo. Rezervacija nije sačuvana.';

    /**
     * GET /payment/result?merchant_transaction_id=...
     * Vraća status (success|failed|pending), user_type (guest|auth), message za failed.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $txId = $request->query('merchant_transaction_id');
        if (! $txId || ! is_string($txId)) {
            return response()->json(['status' => 'failed', 'user_type' => 'guest', 'message' => self::MESSAGE_FAILED], 400);
        }

        $reservation = Reservation::where('merchant_transaction_id', $txId)->first();
        if ($reservation) {
            return response()->json([
                'status' => 'success',
                'user_type' => $reservation->user_id ? 'auth' : 'guest',
                'redirect_guest' => route('reservations.create'),
                'redirect_auth' => route('profile.reservations'),
            ]);
        }

        $temp = TempData::where('merchant_transaction_id', $txId)->first();
        if ($temp) {
            $userType = $temp->user_id ? 'auth' : 'guest';
            if ($temp->status === TempData::STATUS_FAILED) {
                return response()->json([
                    'status' => 'failed',
                    'user_type' => $userType,
                    'message' => self::MESSAGE_FAILED,
                    'redirect_guest' => route('reservations.create'),
                    'redirect_auth' => route('profile.reservations'),
                ]);
            }

            return response()->json([
                'status' => 'pending',
                'user_type' => $userType,
                'redirect_guest' => route('reservations.create'),
                'redirect_auth' => route('profile.reservations'),
            ]);
        }

        return response()->json([
            'status' => 'failed',
            'user_type' => 'guest',
            'message' => self::MESSAGE_FAILED,
            'redirect_guest' => route('reservations.create'),
            'redirect_auth' => route('profile.reservations'),
        ], 404);
    }
}
