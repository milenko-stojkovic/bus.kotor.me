<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TempData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/reservations/retry/{retry_token}
 * Vraća podatke temp_data za auto-popunjavanje forme samo ako je status canceled/expired i nije stariji od N minuta.
 */
class RetryReservationController extends Controller
{
    public function __invoke(Request $request, string $retry_token): JsonResponse
    {
        $validMinutes = config('reservations.retry_token_valid_minutes', 60);
        $cutoff = now()->subMinutes($validMinutes);

        $temp = TempData::where('retry_token', $retry_token)
            ->whereIn('status', [TempData::STATUS_CANCELED, TempData::STATUS_EXPIRED])
            ->where('created_at', '>=', $cutoff)
            ->first();

        if (! $temp) {
            return response()->json(['message' => 'Retry token invalid or expired.'], 404);
        }

        $reservation_date = $temp->reservation_date;
        if ($reservation_date && method_exists($reservation_date, 'format')) {
            $reservation_date = $reservation_date->format('Y-m-d');
        }

        return response()->json([
            'user_name' => $temp->user_name,
            'country' => $temp->country,
            'license_plate' => $temp->license_plate,
            'vehicle_type_id' => $temp->vehicle_type_id,
            'email' => $temp->email,
            'drop_off_time_slot_id' => $temp->drop_off_time_slot_id,
            'pick_up_time_slot_id' => $temp->pick_up_time_slot_id,
            'reservation_date' => $reservation_date,
            'callback_error_code' => $temp->callback_error_code,
            'callback_error_reason' => $temp->callback_error_reason,
        ]);
    }
}
