<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentService;
use App\Http\Requests\CheckoutReservationRequest;
use App\Models\DailyParkingData;
use App\Models\TempData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * Checkout: validacija, provera dostupnosti, temp_data (pending), sync createSession sa gatewayom.
 * Redirect na payment_url odmah; obrada rezultata plaćanja isključivo preko webhook + queue job.
 * Ako gateway spor/nedostupan → 503, bez kreiranja rezervacije.
 */
class CheckoutController extends Controller
{
    /**
     * Validira, proverava kapacitet, kreira temp_data (pending), kreira payment session (sync).
     * Redirect na bank payment page; nikad obrada statusa plaćanja u HTTP request-u.
     */
    public function store(CheckoutReservationRequest $request, PaymentService $paymentService): RedirectResponse|Response|JsonResponse
    {
        $date = $request->validated('reservation_date');
        $dropOffSlotId = $request->validated('drop_off_time_slot_id');

        $daily = DailyParkingData::where('date', $date)
            ->where('time_slot_id', $dropOffSlotId)
            ->first();

        if (! $daily || $daily->availableCapacity() < 1) {
            $message = __('No availability for selected slot.');
            return $request->expectsJson()
                ? response()->json(['message' => $message], 422)
                : response($message, 422);
        }

        $temp = TempData::create([
            'merchant_transaction_id' => $request->validated('merchant_transaction_id'),
            'user_id' => $request->user()?->id,
            'drop_off_time_slot_id' => $dropOffSlotId,
            'pick_up_time_slot_id' => $request->validated('pick_up_time_slot_id'),
            'reservation_date' => $date,
            'user_name' => $request->validated('user_name'),
            'country' => $request->validated('country'),
            'license_plate' => $request->validated('license_plate'),
            'vehicle_type_id' => $request->validated('vehicle_type_id'),
            'email' => $request->validated('email'),
            'status' => TempData::STATUS_PENDING,
        ]);

        $daily->increment('pending');

        $session = $paymentService->createSession($temp);

        if ($session->success && $session->paymentUrl) {
            return redirect()->away($session->paymentUrl);
        }

        $daily->decrement('pending');
        $message = $session->errorMessage ?? 'Payment temporarily unavailable.';
        return $request->expectsJson()
            ? response()->json(['message' => $message], Response::HTTP_SERVICE_UNAVAILABLE)
            : response($message, Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
