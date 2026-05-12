<?php

namespace App\Http\Controllers\Limo;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessLimoAfterPaymentJob;
use App\Services\Limo\LimoPlateCropExtractor;
use App\Services\Limo\LimoPlatePickupService;
use App\Support\QueueMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LimoPlatePickupController extends Controller
{
    /** @var array<string, string> */
    private const PLATE_CONFIRM_ERROR_MESSAGES = [
        'plate_not_registered' => 'Tablica nije pronađena u voznom parku nijedne agencije.',
        'insufficient_advance' => 'Agencija nema dovoljno avansa.',
        'invalid_upload' => 'Fotografija više nije važeća. Pokušajte ponovo.',
        'validation_error' => 'Došlo je do greške. Pokušajte ponovo.',
    ];

    public function plateOcr(Request $request, LimoPlatePickupService $platePickupService): JsonResponse
    {
        try {
            $validated = $request->validate([
                'image' => ['required', 'file', 'image', 'max:5120', 'mimes:jpeg,jpg,png,webp'],
                'gps_lat' => ['sometimes', 'nullable', 'numeric'],
                'gps_lng' => ['sometimes', 'nullable', 'numeric'],
                'device_info' => ['sometimes', 'nullable', 'string', 'max:2000'],
                'plate_crop_left' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10000'],
                'plate_crop_top' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10000'],
                'plate_crop_width' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10000'],
                'plate_crop_height' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10000'],
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
            return response()->json([
                'status' => 'error',
                'code' => 'generic',
                'message' => 'Niste prijavljeni. Osvježite stranicu i prijavite se.',
            ], 401);
        }

        $file = $request->file('image');
        if ($file === null) {
            return response()->json([
                'status' => 'error',
                'code' => 'validation_error',
                'message' => 'Došlo je do greške. Pokušajte ponovo.',
            ], 422);
        }

        $plateCropBp = null;
        if ($request->filled('plate_crop_left') && $request->filled('plate_crop_top')
            && $request->filled('plate_crop_width') && $request->filled('plate_crop_height')) {
            $plateCropBp = [
                'left' => (int) $validated['plate_crop_left'],
                'top' => (int) $validated['plate_crop_top'],
                'width' => (int) $validated['plate_crop_width'],
                'height' => (int) $validated['plate_crop_height'],
            ];
            if (! LimoPlateCropExtractor::validateBasisPoints($plateCropBp)) {
                return response()->json([
                    'status' => 'error',
                    'code' => 'validation_error',
                    'message' => 'Neispravan izrez tablice. Pokušajte ponovo.',
                ], 422);
            }
        }

        $result = $platePickupService->processUpload(
            $file,
            isset($validated['gps_lat']) ? (string) $validated['gps_lat'] : null,
            isset($validated['gps_lng']) ? (string) $validated['gps_lng'] : null,
            $validated['device_info'] ?? null,
            (int) $admin->id,
            (bool) config('app.debug'),
            $plateCropBp,
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
            return response()->json([
                'status' => 'error',
                'code' => 'generic',
                'message' => 'Niste prijavljeni. Osvježite stranicu i prijavite se.',
            ], 401);
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
            $code = $result['code'];

            return response()->json([
                'status' => 'error',
                'code' => $code,
                'message' => self::PLATE_CONFIRM_ERROR_MESSAGES[$code] ?? 'Došlo je do greške. Pokušajte ponovo.',
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
