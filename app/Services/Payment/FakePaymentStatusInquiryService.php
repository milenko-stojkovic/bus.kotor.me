<?php

namespace App\Services\Payment;

use App\Contracts\PaymentStatusInquiryService;

/**
 * Fake bank – nema status inquiry endpoint. Vraća null (ne proveravaj).
 */
class FakePaymentStatusInquiryService implements PaymentStatusInquiryService
{
    public function inquire(string $merchantTransactionId): ?string
    {
        return null;
    }
}
