<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessReservationAfterPaymentJob;
use App\Models\Reservation;
use App\Models\TempData;
use App\Support\ReservationInvoiceAmount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LateSuccessController extends Controller
{
    public function index(Request $request): View
    {
        $date = $request->string('date')->toString();
        $status = $request->string('status')->toString();
        $resolutionReason = $request->string('resolution_reason')->toString();

        $rows = TempData::query()
            ->with(['vehicleType', 'dropOffTimeSlot', 'pickUpTimeSlot'])
            ->whereIn('status', [
                TempData::STATUS_LATE_SUCCESS,
                TempData::STATUS_LATE_MANUAL_REVIEW,
                TempData::STATUS_LATE_REJECTED,
                TempData::STATUS_PROCESSED,
            ])
            ->when($date !== '', fn ($q) => $q->whereDate('reservation_date', $date))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($resolutionReason !== '', fn ($q) => $q->where('resolution_reason', $resolutionReason))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.late-success.index', [
            'rows' => $rows,
            'filters' => [
                'date' => $date,
                'status' => $status,
                'resolution_reason' => $resolutionReason,
            ],
        ]);
    }

    public function show(int $id): View
    {
        $row = TempData::query()
            ->with(['vehicleType.translations', 'dropOffTimeSlot', 'pickUpTimeSlot', 'user'])
            ->findOrFail($id);

        return view('admin.late-success.show', ['row' => $row]);
    }

    public function forceCreate(int $id): RedirectResponse
    {
        $result = DB::transaction(function () use ($id): array {
            $temp = TempData::query()->whereKey($id)->lockForUpdate()->first();
            if (! $temp) {
                return ['ok' => false, 'message' => __('Zapis nije pronađen.')];
            }
            if ($temp->status !== TempData::STATUS_LATE_MANUAL_REVIEW) {
                return ['ok' => false, 'message' => __('Status mora biti late_manual_review.')];
            }

            $existing = Reservation::where('merchant_transaction_id', $temp->merchant_transaction_id)->first();
            if ($existing) {
                return [
                    'ok' => true,
                    'message' => 'Reservation already exists, no action taken.',
                    'reservation_id' => $existing->id,
                    'created_now' => false,
                ];
            }

            $reservation = Reservation::create([
                'user_id' => $temp->user_id,
                'vehicle_id' => null,
                'merchant_transaction_id' => $temp->merchant_transaction_id,
                'drop_off_time_slot_id' => $temp->drop_off_time_slot_id,
                'pick_up_time_slot_id' => $temp->pick_up_time_slot_id,
                'reservation_date' => $temp->reservation_date,
                'user_name' => $temp->user_name,
                'country' => $temp->country,
                'license_plate' => $temp->license_plate,
                'vehicle_type_id' => $temp->vehicle_type_id,
                'email' => $temp->email,
                'preferred_locale' => $temp->preferred_locale,
                'status' => 'paid',
                'invoice_amount' => ReservationInvoiceAmount::snapshotForNewReservation('paid', $temp->vehicle_type_id),
                'email_sent' => \App\Models\Reservation::EMAIL_NOT_SENT,
            ]);

            $from = $temp->status;
            $temp->update([
                'status' => TempData::STATUS_PROCESSED,
                'resolution_reason' => 'admin_forced',
            ]);
            TempData::logStateTransition($temp->merchant_transaction_id, $from, TempData::STATUS_PROCESSED, 'Admin manual review forced create');

            return [
                'ok' => true,
                'message' => __('Rezervacija je kreirana admin override-om.'),
                'reservation_id' => $reservation->id,
                'created_now' => true,
            ];
        });

        if (! $result['ok']) {
            return redirect()->back()->with('error', $result['message']);
        }

        if (($result['created_now'] ?? false) && ! empty($result['reservation_id'])) {
            ProcessReservationAfterPaymentJob::dispatch((int) $result['reservation_id']);
        }

        return redirect()->back()->with('message', $result['message']);
    }

    public function reject(int $id): RedirectResponse
    {
        $result = DB::transaction(function () use ($id): array {
            $temp = TempData::query()->whereKey($id)->lockForUpdate()->first();
            if (! $temp) {
                return ['ok' => false, 'message' => __('Zapis nije pronađen.')];
            }
            if ($temp->status !== TempData::STATUS_LATE_MANUAL_REVIEW) {
                return ['ok' => false, 'message' => __('Status mora biti late_manual_review.')];
            }

            $from = $temp->status;
            $temp->update([
                'status' => TempData::STATUS_LATE_REJECTED,
                'resolution_reason' => 'admin_rejected',
            ]);
            TempData::logStateTransition($temp->merchant_transaction_id, $from, TempData::STATUS_LATE_REJECTED, 'Admin manual review rejected');

            return ['ok' => true, 'message' => __('Late manual review zapis je odbijen.')];
        });

        if (! $result['ok']) {
            return redirect()->back()->with('error', $result['message']);
        }

        return redirect()->back()->with('message', $result['message']);
    }
}
