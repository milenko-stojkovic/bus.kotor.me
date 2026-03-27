<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Services\FiscalizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FakeFiscalScenarioController extends Controller
{
    private const CONFIG_KEY = 'fiscal_fake_scenario';

    /** @var array<int, string> */
    private const ALLOWED = [
        'success',
        'deposit_missing',
        'already_fiscalized',
        'validation_error',
        'provider_down',
        'tax_server_error',
        'temporary_service_down',
        'timeout',
        'malformed_response',
    ];

    public function index(): View
    {
        abort_unless(app()->environment('local'), 404);

        $envScenario = (string) config('services.fiscalization.fake_scenario', '');
        $sessionScenario = request()->session()->get(self::CONFIG_KEY);
        $sessionScenario = is_string($sessionScenario) ? $sessionScenario : null;
        $tx = request()->query('merchant_transaction_id');
        $tx = is_string($tx) ? trim($tx) : null;

        $reservation = null;
        if (is_string($tx) && $tx !== '') {
            $reservation = Reservation::query()
                ->where('merchant_transaction_id', $tx)
                ->first();
        }

        return view('payment.fake-fiscal', [
            'allowed' => self::ALLOWED,
            'env_scenario' => $envScenario,
            'session_scenario' => $sessionScenario,
            'merchant_transaction_id' => $tx,
            'reservation' => $reservation,
        ]);
    }

    public function set(Request $request): RedirectResponse
    {
        abort_unless(app()->environment('local'), 404);

        $scenario = (string) $request->validate([
            'scenario' => ['required', 'string', 'in:'.implode(',', self::ALLOWED)],
        ])['scenario'];

        // SystemConfig stores integer; we persist scenario in session instead (web-only).
        $request->session()->put(self::CONFIG_KEY, $scenario);

        $tx = $request->input('merchant_transaction_id');
        $tx = is_string($tx) ? trim($tx) : null;

        if (is_string($tx) && $tx !== '') {
            return redirect()->to(route('payment.fake-fiscal', ['merchant_transaction_id' => $tx], false))
                ->with('message', 'Fake fiscal scenario set: '.$scenario);
        }

        return redirect()->back()->with('message', 'Fake fiscal scenario set: '.$scenario);
    }

    public function clear(Request $request): RedirectResponse
    {
        abort_unless(app()->environment('local'), 404);

        $request->session()->forget(self::CONFIG_KEY);

        $tx = $request->input('merchant_transaction_id');
        $tx = is_string($tx) ? trim($tx) : null;

        if (is_string($tx) && $tx !== '') {
            return redirect()->to(route('payment.fake-fiscal', ['merchant_transaction_id' => $tx], false))
                ->with('message', 'Fake fiscal scenario cleared.');
        }

        return redirect()->back()->with('message', 'Fake fiscal scenario cleared.');
    }

    public function apply(Request $request, FiscalizationService $fiscal): RedirectResponse
    {
        abort_unless(app()->environment('local'), 404);

        $data = $request->validate([
            'scenario' => ['required', 'string', 'in:'.implode(',', self::ALLOWED)],
            'merchant_transaction_id' => ['required', 'string'],
        ]);

        $scenario = (string) $data['scenario'];
        $tx = trim((string) $data['merchant_transaction_id']);
        if ($tx === '') {
            return redirect()->back()->with('error', 'Missing transaction id.');
        }

        $request->session()->put(self::CONFIG_KEY, $scenario);

        $reservation = Reservation::query()
            ->where('merchant_transaction_id', $tx)
            ->first();

        if (! $reservation) {
            return redirect()->to(route('payment.fake-fiscal', ['merchant_transaction_id' => $tx], false))
                ->with('error', 'Reservation not found for this transaction id.');
        }

        $result = $fiscal->tryFiscalize($reservation);

        if (isset($result['fiscal_jir'], $result['fiscal_ikof'])) {
            $reservation->fill([
                'fiscal_jir' => $result['fiscal_jir'],
                'fiscal_ikof' => $result['fiscal_ikof'],
                'fiscal_qr' => $result['fiscal_qr'] ?? null,
                'fiscal_operator' => $result['fiscal_operator'] ?? null,
                'fiscal_date' => $result['fiscal_date'] ?? now(),
            ]);
            $reservation->save();

            // Success: take user back to start a new reservation.
            return redirect()->to(route('landing', [], false))
                ->with('message', 'Fake fiscal SUCCESS simulated ('.$scenario.'). You can create a new reservation now.');
        }

        $reason = $result['resolution_reason'] ?? ($result['category'] ?? 'error');
        $error = $result['error'] ?? 'Simulated fiscal error.';

        return redirect()->to(route('payment.fake-fiscal', ['merchant_transaction_id' => $tx], false))
            ->with('error', 'Fake fiscal ERROR simulated ('.$scenario.'): '.$reason.' — '.$error);
    }
}

