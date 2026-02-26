<?php

namespace App\Http\Controllers;

use App\Services\Payment\PaymentResultResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Stranica na koju korisnik stiže nakon redirecta sa banke (success/cancel URL).
 * Status se uvek čita iz baze – UI nije izvor istine. Ako korisnik zatvori tab tokom redirecta
 * i vrati se kasnije, ponovo se pita baza: reservation postoji → success; inače pending / retry ili failed.
 */
class PaymentReturnController extends Controller
{
    /**
     * GET /payment/return?merchant_transaction_id=...
     * Prikazuje success / pending / failed ekran na osnovu trenutnog stanja u bazi.
     */
    public function __invoke(Request $request, PaymentResultResolver $resolver): View|RedirectResponse
    {
        $txId = $request->query('merchant_transaction_id');
        if (! $txId || ! is_string($txId)) {
            return redirect()->route('reservations.create')->with('error', PaymentResultResolver::MESSAGE_FAILED);
        }

        $result = $resolver->resolve($txId);
        if ($result === null) {
            return redirect()->route('reservations.create')->with('error', PaymentResultResolver::MESSAGE_FAILED);
        }

        return view('payment.return', [
            'merchant_transaction_id' => $txId,
            'result' => $result,
        ]);
    }
}
