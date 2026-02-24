<?php

namespace App\Services\Payment;

use App\Contracts\PaymentResult;
use App\Contracts\PaymentService;
use App\Contracts\PaymentSessionResult;
use App\Models\TempData;
use Illuminate\Support\Facades\URL;

/**
 * Simulacija plaćanja. createSession vraća URL na fake bank page; pay() za webhook flow.
 * Pravi gateway se ubacuje preko config payment.provider = real (RealPaymentProvider).
 */
class FakePaymentProvider implements PaymentService
{
    /**
     * Vraća URL na fake bank stranicu za test (redirect odmah nakon "Pay").
     */
    public function createSession(TempData $tempData): PaymentSessionResult
    {
        $url = URL::route('payment.fake-bank', ['tx' => $tempData->merchant_transaction_id]);

        return PaymentSessionResult::ok($url);
    }

    /**
     * Simulira rezultat: default success. Za testiranje failed/timeout možeš proveriti
     * merchant_transaction_id (npr. suffix _fail ili _timeout) ili env.
     */
    public function pay(TempData $tempData): PaymentResult
    {
        $txId = $tempData->merchant_transaction_id ?? '';

        if (str_ends_with($txId, '_fail')) {
            return PaymentResult::failed('Simulated failure');
        }
        if (str_ends_with($txId, '_timeout')) {
            return PaymentResult::timeout('Simulated timeout');
        }

        return PaymentResult::success();
    }
}
