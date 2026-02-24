<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\TempData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Polling endpoint za status rezervacije. UI periodično poziva sa merchant_transaction_id.
 * Vraća: pending | success | failed | late_success. Na success vraća i reservation_id.
 */
class ReservationStatusController extends Controller
{
    /**
     * Status po merchant_transaction_id. Za polling iz UI-ja.
     */
    public function show(Request $request, string $merchantTransactionId): JsonResponse
    {
        $reservation = Reservation::where('merchant_transaction_id', $merchantTransactionId)->first();
        if ($reservation) {
            return response()->json([
                'status' => 'success',
                'reservation_id' => $reservation->id,
            ]);
        }

        $temp = TempData::where('merchant_transaction_id', $merchantTransactionId)->first();
        if ($temp) {
            return response()->json([
                'status' => $temp->status,
            ]);
        }

        return response()->json(['error' => 'Not found'], 404);
    }
}
