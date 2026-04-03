<?php

namespace App\Http\Controllers;

use App\Services\Payment\PaymentResultResolver;
use App\Support\CheckoutResultFlash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Stranica na koju korisnik stiže nakon redirecta sa banke (success/cancel URL).
 * Status se uvek čita iz baze – UI nije izvor istine. Ako korisnik zatvori tab tokom redirecta
 * i vrati se kasnije, ponovo se pita baza: reservation postoji → success; inače pending / retry ili failed.
 *
 * Uspeh / neuspeh / kasni uspeh: redirect na guest.reserve ili panel.reservations sa checkout_banner flash.
 * Pending: ostaje ovde sa polling-om dok callback ne kreira rezervaciju.
 */
class PaymentReturnController extends Controller
{
    /**
     * GET /payment/return?merchant_transaction_id=...
     */
    public function __invoke(Request $request, PaymentResultResolver $resolver): View|RedirectResponse
    {
        $txId = $request->query('merchant_transaction_id');
        if (! $txId || ! is_string($txId)) {
            return $this->redirectWithBanner(CheckoutResultFlash::invalidPaymentReturn());
        }

        $result = $resolver->resolve($txId);
        if ($result === null) {
            return $this->redirectWithBanner(CheckoutResultFlash::invalidPaymentReturn());
        }

        if ($result['status'] === 'pending') {
            return view('payment.return', [
                'merchant_transaction_id' => $txId,
                'result' => $result,
            ]);
        }

        $destination = $this->destinationUrl($result['user_type'] ?? 'guest');

        if ($result['status'] === 'failed') {
            if (($result['user_type'] ?? '') === 'guest' && ! empty($result['retry_token'] ?? null)) {
                $destination = route('guest.reserve', ['retry_token' => $result['retry_token']], false);
            }

            $banner = CheckoutResultFlash::forPaymentFailure($result['resolution_reason'] ?? null);

            return redirect()->to($destination)->with('checkout_banner', $banner);
        }

        if ($result['status'] === 'late_success') {
            return redirect()->to($destination)->with('checkout_banner', CheckoutResultFlash::lateSuccess());
        }

        // success
        $banner = CheckoutResultFlash::forReservationSuccess(
            (bool) ($result['is_free_reservation'] ?? false),
            (bool) ($result['fiscal_complete'] ?? true),
            (bool) ($result['fiscal_delayed_known'] ?? false),
        );

        return redirect()->to($destination)->with('checkout_banner', $banner);
    }

    /**
     * @param  'guest'|'auth'  $userType
     */
    private function destinationUrl(string $userType): string
    {
        if ($userType === 'auth') {
            return route('panel.reservations', [], false);
        }

        return route('guest.reserve', [], false);
    }

    /**
     * Kad nema merchant_transaction_id ili reda u bazi — ne znamo guest vs auth; koristi trenutnu sesiju.
     */
    private function redirectWithBanner(array $banner): RedirectResponse
    {
        $destination = auth()->check()
            ? route('panel.reservations', [], false)
            : route('guest.reserve', [], false);

        return redirect()->to($destination)->with('checkout_banner', $banner);
    }
}
