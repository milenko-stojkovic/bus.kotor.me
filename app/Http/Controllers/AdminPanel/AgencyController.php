<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminPanel\AdminAgencyAdvanceCorrectionRequest;
use App\Models\AgencyAdvanceTopup;
use App\Models\AgencyAdvanceTransaction;
use App\Models\User;
use App\Services\AgencyAdvance\AdvanceTopupConfirmationService;
use App\Services\AgencyAdvance\AgencyAdvanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AgencyController extends Controller
{
    public function index(): View
    {
        $q = User::query()
            ->select('users.*')
            ->leftJoin('agency_advance_transactions as aat', 'aat.agency_user_id', '=', 'users.id')
            ->addSelect(DB::raw('COALESCE(SUM(aat.amount), 0) as advance_balance'))
            ->withCount('reservations')
            ->groupBy('users.id')
            ->orderBy('users.name');

        $users = $q->paginate(20)->withQueryString();

        return view('admin-panel.agencies.index', [
            'users' => $users,
            'advanceEnabled' => (bool) config('features.advance_payments'),
        ]);
    }

    public function show(User $user, AgencyAdvanceService $advance): View
    {
        $advanceEnabled = (bool) config('features.advance_payments');

        $balance = null;
        $ledger = collect();
        $topups = collect();

        if ($advanceEnabled) {
            $balance = $advance->balance((int) $user->id);

            $ledger = AgencyAdvanceTransaction::query()
                ->where('agency_user_id', $user->id)
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();

            $topups = AgencyAdvanceTopup::query()
                ->where('agency_user_id', $user->id)
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();
        }

        return view('admin-panel.agencies.show', [
            'user' => $user,
            'advanceEnabled' => $advanceEnabled,
            'balance' => $balance,
            'ledger' => $ledger,
            'topups' => $topups,
        ]);
    }

    public function storeAdvanceCorrection(User $user, AdminAgencyAdvanceCorrectionRequest $request, AgencyAdvanceService $advance): RedirectResponse
    {
        if (! (bool) config('features.advance_payments')) {
            abort(404);
        }

        $validated = $request->validated();
        $amountRaw = (float) $validated['amount'];
        $direction = (string) $validated['direction'];
        $reason = trim((string) $validated['reason']);

        $abs = number_format($amountRaw, 2, '.', '');
        $signed = $direction === 'decrease'
            ? number_format(-1 * (float) $abs, 2, '.', '')
            : $abs;

        DB::transaction(function () use ($user, $advance, $direction, $abs, $signed, $reason, $request): void {
            // Serialize corrections per agency.
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            if ($direction === 'decrease') {
                $bal = (float) $advance->balance((int) $user->id);
                if ($bal + 0.000001 < (float) $abs) {
                    throw ValidationException::withMessages([
                        'amount' => ['Korekcija ne može umanjiti saldo ispod 0.00 EUR.'],
                    ]);
                }
            }

            AgencyAdvanceTransaction::query()->create([
                'agency_user_id' => $user->id,
                'amount' => $signed,
                'type' => AgencyAdvanceTransaction::TYPE_CORRECTION,
                'reference_type' => null,
                'reference_id' => null,
                'merchant_transaction_id' => null,
                'note' => $reason,
                'created_by_admin_id' => $request->user('panel_admin')?->id,
            ]);

            Log::channel('payments')->info('advance_balance_correction_created', [
                'agency_user_id' => $user->id,
                'admin_id' => $request->user('panel_admin')?->id,
                'direction' => $direction,
                'amount_abs' => $abs,
                'amount_signed' => $signed,
            ]);
        });

        return redirect()
            ->to(route('panel_admin.agencies.show', $user, false))
            ->with('status', 'Korekcija avansa je evidentirana.');
    }

    public function resendAdvanceTopupConfirmation(User $user, AgencyAdvanceTopup $topup, AdvanceTopupConfirmationService $confirm): RedirectResponse
    {
        if (! (bool) config('features.advance_payments')) {
            abort(404);
        }

        if ((int) $topup->agency_user_id !== (int) $user->id) {
            abort(404);
        }

        if ($topup->status !== AgencyAdvanceTopup::STATUS_PAID) {
            return redirect()
                ->to(route('panel_admin.agencies.show', $user, false))
                ->with('error', 'Potvrda se šalje samo za uplate sa statusom paid.');
        }

        if ($topup->confirmation_sent_at !== null) {
            return redirect()
                ->to(route('panel_admin.agencies.show', $user, false))
                ->with('message', 'Potvrda je već poslata.');
        }

        $result = $confirm->sendIfNeeded((string) $topup->merchant_transaction_id);

        return match ($result) {
            'sent' => redirect()
                ->to(route('panel_admin.agencies.show', $user, false))
                ->with('status', 'Potvrda je poslata.'),
            'already_sent' => redirect()
                ->to(route('panel_admin.agencies.show', $user, false))
                ->with('message', 'Potvrda je već poslata.'),
            default => redirect()
                ->to(route('panel_admin.agencies.show', $user, false))
                ->with('error', 'Slanje potvrde nije uspjelo. Pokušajte ponovo.'),
        };
    }
}

