<?php

namespace App\Services\Payment;

use App\Contracts\PaymentStatusInquiryService;

/**
 * Fake bank – nema status inquiry endpoint.
 */
class FakePaymentStatusInquiryService implements PaymentStatusInquiryService
{
    public function isImplemented(): bool
    {
        return false;
    }

    public function inquire(string $merchantTransactionId): ?string
    {
        return null;
    }
}
