<?php

namespace App\Contracts;

/**
 * Status inquiry kod banke – provera statusa plaćanja po merchant_transaction_id.
 * Koristi se kada callback od banke nikad ne stigne (timeout, mreža, firewall).
 */
interface PaymentStatusInquiryService
{
    /**
     * Da li je HTTP status inquiry implementiran (Bankart). Dok je false, cron ne poziva inquire()
     * i ne menja status — samo eventualni log „pending too long“.
     */
    public function isImplemented(): bool;

    /**
     * Proveri status plaćanja kod banke. Vraća 'success' | 'failed' | null (nepoznat/error).
     * Pozivati samo kada {@see isImplemented()} true.
     */
    public function inquire(string $merchantTransactionId): ?string;
}
