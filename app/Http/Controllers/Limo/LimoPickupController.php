<?php

namespace App\Http\Controllers\Limo;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessLimoAfterPaymentJob;
use App\Services\Limo\LimoPickupService;
use App\Support\QueueMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LimoPickupController extends Controller
{
    public function pickupByQr(Request $request, LimoPickupService $limoPickupService): JsonResponse
    {
        try {
            $validated = $request->validate([
                'token' => ['required', 'string'],
                'license_plate' => ['sometimes', 'nullable', 'string', 'max:64'],
                'device_info' => ['sometimes', 'nullable', 'string', 'max:2000'],
                'gps_lat' => ['sometimes', 'nullable', 'numeric'],
                'gps_lng' => ['sometimes', 'nullable', 'numeric'],
            ]);
        } catch (ValidationException) {
            return response()->json([
                'status' => 'error',
                'code' => 'validation_error',
                'message' => 'Došlo je do greške. Pokušajte ponovo.',
            ], 422);
        }

        $admin = $request->user('panel_admin');
        if ($admin === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $result = $limoPickupService->processQrPickup(
            $validated['token'],
            [
                'license_plate' => $validated['license_plate'] ?? null,
                'device_info' => $validated['device_info'] ?? null,
                'gps_lat' => isset($validated['gps_lat']) ? (string) $validated['gps_lat'] : null,
                'gps_lng' => isset($validated['gps_lng']) ? (string) $validated['gps_lng'] : null,
            ],
            (int) $admin->id,
        );

        if (! $result['success']) {
            return response()->json([
                'status' => 'error',
                'code' => $result['code'],
            ], 422);
        }

        $bankFake = (string) config('services.bank.driver', 'fake') === 'fake';
        $fiscalFake = (string) config('services.fiscalization.driver', 'fake') === 'fake';
        if ($bankFake && $fiscalFake) {
            QueueMode::dispatchForFakeE2e(new ProcessLimoAfterPaymentJob($result['event_id']));
        } else {
            ProcessLimoAfterPaymentJob::dispatch($result['event_id']);
        }

        return response()->json([
            'status' => 'ok',
            'merchant_transaction_id' => $result['merchant_transaction_id'],
            'remaining_balance' => $result['remaining_balance'],
        ]);
    }
}
