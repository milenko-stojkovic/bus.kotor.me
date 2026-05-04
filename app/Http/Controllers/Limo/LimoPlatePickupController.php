<?php

namespace App\Http\Controllers\Limo;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessLimoAfterPaymentJob;
use App\Services\Limo\LimoPlatePickupService;
use App\Support\QueueMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LimoPlatePickupController extends Controller
{
    public function plateOcr(Request $request, LimoPlatePickupService $platePickupService): JsonResponse
    {
        try {
            $validated = $request->validate([
                'image' => ['required', 'file', 'image', 'max:5120', 'mimes:jpeg,jpg,png,webp'],
                'gps_lat' => ['sometimes', 'nullable', 'numeric'],
                'gps_lng' => ['sometimes', 'nullable', 'numeric'],
                'device_info' => ['sometimes', 'nullable', 'string', 'max:2000'],
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

        $file = $request->file('image');
        if ($file === null) {
            return response()->json([
                'status' => 'error',
                'code' => 'validation_error',
                'message' => 'Došlo je do greške. Pokušajte ponovo.',
            ], 422);
        }

        $result = $platePickupService->processUpload(
            $file,
            isset($validated['gps_lat']) ? (string) $validated['gps_lat'] : null,
            isset($validated['gps_lng']) ? (string) $validated['gps_lng'] : null,
            $validated['device_info'] ?? null,
            (int) $admin->id,
        );

        return response()->json($result);
    }

    public function plateConfirm(Request $request, LimoPlatePickupService $platePickupService): JsonResponse
    {
        try {
            $validated = $request->validate([
                'upload_token' => ['required', 'string'],
                'license_plate' => ['required', 'string', 'max:50'],
                'gps_lat' => ['sometimes', 'nullable', 'numeric'],
                'gps_lng' => ['sometimes', 'nullable', 'numeric'],
                'device_info' => ['sometimes', 'nullable', 'string', 'max:2000'],
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

        $result = $platePickupService->confirmPlate(
            $validated['upload_token'],
            $validated['license_plate'],
            [
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
