<?php

namespace App\Http\Controllers;

use App\Jobs\PaymentCallbackJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * SAMO za test: simulacija banke (fake bank stranica). Frontend NIKAD ne sme da poziva bank callback.
 *
 * Bank callback = POST /api/payments/callback = machine-to-machine only.
 * Ovaj endpoint = POST /payment/fake-bank/complete = samo za UI flow fake banke (Success/Fail dugmić).
 * Isti job (PaymentCallbackJob) se dispatch-uje; redirect za UX na reservation-status.
 */
class FakeBankCompleteController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'merchant_transaction_id' => ['required', 'string', 'max:64'],
            'status' => ['required', 'string', 'in:success,failed'],
        ]);

        $rawPayload = $request->only(['merchant_transaction_id', 'status']);
        PaymentCallbackJob::dispatch($validated, $rawPayload);

        return redirect()->route('payment.return', [
            'merchant_transaction_id' => $validated['merchant_transaction_id'],
        ]);
    }
}
