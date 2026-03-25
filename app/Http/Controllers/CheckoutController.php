<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentService;
use App\Exceptions\NoCapacityException;
use App\Helpers\LocaleHelper;
use App\Http\Requests\CheckoutReservationRequest;
use App\Models\DailyParkingData;
use App\Models\TempData;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Checkout: validacija, dostupnost, temp_data (pending), sync createSession, redirect na banku.
 *
 * Namjerno V1: createSession se poziva sync u web requestu (nema queue job-a za init).
 * V. docs/payment-v1-production-audit.md i docs/payment-architecture.md.
 *
 * Race condition: lock na daily_parking_data; rezervacija tek nakon SUCCESS callbacka.
 * Dupli klik: postojeći pending za slot+user → isti payment link.
 */
class CheckoutController extends Controller
{
    /**
     * Validira. Atomic claim: transakcija + lockForUpdate na daily_parking_data, provera kapaciteta,
     * kreiranje temp_data, increment pending. createSession van transakcije.
     */
    public function store(CheckoutReservationRequest $request, PaymentService $paymentService): RedirectResponse|Response|JsonResponse
    {
        $date = $request->validated('reservation_date');
        $dropOffSlotId = $request->validated('drop_off_time_slot_id');
        $snapshot = $this->resolveSnapshotInput($request);

        // Već postoji pending za isti slot + user/email → vrati postojeći payment link (bez locka)
        $existingBySlot = $this->findExistingPendingForSlot($request, $date, $dropOffSlotId);
        if ($existingBySlot) {
            $session = $paymentService->createSession($existingBySlot);
            if ($session->success && $session->paymentUrl) {
                return redirect()->away($session->paymentUrl);
            }
        }

        // merchant_transaction_id i retry_token uvek generiše backend (pre job-a i redirect-a)
        $merchantTransactionId = Str::uuid()->toString();
        $retryToken = Str::uuid()->toString();

        try {
            $temp = DB::transaction(function () use ($request, $date, $dropOffSlotId, $merchantTransactionId, $retryToken, $snapshot) {
                // Atomic: lock red za (date, time_slot) da drugi request sačeka
                $daily = DailyParkingData::where('date', $date)
                    ->where('time_slot_id', $dropOffSlotId)
                    ->lockForUpdate()
                    ->first();

                if (! $daily || $daily->availableCapacity() < 1) {
                    throw new NoCapacityException(__('No availability for selected slot.'));
                }

                $preferredLocale = $request->user()
                    ? ($request->user()->lang ?? 'en')
                    : ($request->session()->get('locale') ?: app()->getLocale());
                if (! LocaleHelper::isValid($preferredLocale)) {
                    $preferredLocale = 'en';
                }

                $temp = TempData::create([
                    'merchant_transaction_id' => $merchantTransactionId,
                    'retry_token' => $retryToken,
                    'user_id' => $request->user()?->id,
                    'vehicle_id' => $snapshot['vehicle_id'],
                    'drop_off_time_slot_id' => $dropOffSlotId,
                    'pick_up_time_slot_id' => $request->validated('pick_up_time_slot_id'),
                    'reservation_date' => $date,
                    'user_name' => $snapshot['user_name'],
                    'country' => $snapshot['country'],
                    'license_plate' => $snapshot['license_plate'],
                    'vehicle_type_id' => $snapshot['vehicle_type_id'],
                    'email' => $snapshot['email'],
                    'preferred_locale' => $preferredLocale,
                    'status' => TempData::STATUS_PENDING,
                ]);

                $daily->increment('pending');

                Log::channel('payments')->info('Payment init', [
                    'merchant_transaction_id' => $merchantTransactionId,
                    'retry_token' => $retryToken,
                ]);

                return $temp;
            });
        } catch (NoCapacityException $e) {
            $message = $e->getMessage();
            return $request->expectsJson()
                ? response()->json(['message' => $message], 422)
                : response($message, 422);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Dupli klik: drugi request ubacio isti merchant_transaction_id → koristi postojeći red
            $existingByMtid = TempData::where('merchant_transaction_id', $merchantTransactionId)
                ->where('status', TempData::STATUS_PENDING)
                ->first();
            if ($existingByMtid) {
                $session = $paymentService->createSession($existingByMtid);
                if ($session->success && $session->paymentUrl) {
                    return redirect()->away($session->paymentUrl);
                }
            }
            throw $e;
        }

        $session = $paymentService->createSession($temp);

        if ($session->success && $session->paymentUrl) {
            return redirect()->away($session->paymentUrl);
        }

        // createSession nije uspeo → vrati hold (decrement pending)
        DailyParkingData::where('date', $date)
            ->where('time_slot_id', $dropOffSlotId)
            ->decrement('pending');
        $message = $session->errorMessage ?? 'Payment temporarily unavailable.';
        return $request->expectsJson()
            ? response()->json(['message' => $message], Response::HTTP_SERVICE_UNAVAILABLE)
            : response($message, Response::HTTP_SERVICE_UNAVAILABLE);
    }

    /** Pending temp_data za isti slot (reservation_date + drop_off) i isti user (user_id ili email). */
    private function findExistingPendingForSlot(CheckoutReservationRequest $request, string $date, int $dropOffSlotId): ?TempData
    {
        $query = TempData::where('reservation_date', $date)
            ->where('drop_off_time_slot_id', $dropOffSlotId)
            ->where('status', TempData::STATUS_PENDING);

        if ($request->user()) {
            $query->where('user_id', $request->user()->id);
        } else {
            $query->where('email', $request->validated('email'));
        }

        return $query->first();
    }

    /**
     * Snapshot source of truth: auth user can choose saved vehicle, otherwise manual form input.
     *
     * @return array{vehicle_id:int|null,user_name:string,country:string,license_plate:string,vehicle_type_id:int,email:string}
     */
    private function resolveSnapshotInput(CheckoutReservationRequest $request): array
    {
        $vehicleId = $request->validated('vehicle_id');
        if ($request->user() && $vehicleId) {
            $vehicle = Vehicle::query()
                ->where('user_id', $request->user()->id)
                ->findOrFail($vehicleId);

            return [
                'vehicle_id' => $vehicle->id,
                'user_name' => (string) ($request->user()->name ?? ''),
                'country' => (string) ($request->user()->country ?? ''),
                'license_plate' => $vehicle->license_plate,
                'vehicle_type_id' => (int) $vehicle->vehicle_type_id,
                'email' => (string) ($request->user()->email ?? ''),
            ];
        }

        return [
            'vehicle_id' => null,
            'user_name' => (string) $request->validated('user_name'),
            'country' => (string) $request->validated('country'),
            'license_plate' => (string) $request->validated('license_plate'),
            'vehicle_type_id' => (int) $request->validated('vehicle_type_id'),
            'email' => (string) $request->validated('email'),
        ];
    }
}
