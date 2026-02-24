<?php

namespace App\Services\Payment;

use App\Contracts\PaymentResult;
use App\Contracts\PaymentService;
use App\Contracts\PaymentSessionResult;
use App\Models\TempData;
use RuntimeException;

/**
 * Placeholder za pravi payment gateway. Kad budeš spreman, zameni ovu implementaciju stvarnim API pozivom.
 * Config: payment.provider = real. Dok ne implementiraš, createSession vraća unavailable; pay() baca izuzetak.
 */
class RealPaymentProvider implements PaymentService
{
    public function createSession(TempData $tempData): PaymentSessionResult
    {
        return PaymentSessionResult::unavailable('Real payment gateway not configured.');
    }

    public function pay(TempData $tempData): PaymentResult
    {
        throw new RuntimeException('Real payment provider not implemented. Set PAYMENT_PROVIDER=fake or implement gateway call here.');
    }
}
