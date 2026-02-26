<?php

namespace App\Contracts;

/**
 * Status inquiry kod banke – provera statusa plaćanja po merchant_transaction_id.
 * Koristi se kada callback od banke nikad ne stigne (timeout, mreža, firewall).
 */
interface PaymentStatusInquiryService
{
    /**
     * Proveri status plaćanja kod banke. Vraća 'success' | 'failed' | null (nepoznat/error).
     */
    public function inquire(string $merchantTransactionId): ?string;
}
