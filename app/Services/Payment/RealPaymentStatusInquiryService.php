<?php

namespace App\Services\Payment;

use App\Contracts\PaymentStatusInquiryService;
use App\Support\HttpOutboundConfig;

/**
 * Bankart status inquiry — hook za budući HTTP poziv.
 *
 * Kada implementiraš:
 * 1. Postavi {@see isImplemented()} na true.
 * 2. Koristi timeoute: {@see HttpOutboundConfig::bankart('status_inquiry')}.
 * 3. Vrati 'success' | 'failed' | null — bez automatske promene statusa van {@see \App\Console\Commands\CheckPendingPaymentStatus}.
 * 4. Ne menjaj temp_data ovde direktno; success flow ostaje {@see \App\Services\Payment\PaymentSuccessHandler::handle()}.
 */
class RealPaymentStatusInquiryService implements PaymentStatusInquiryService
{
    public function isImplemented(): bool
    {
        return false;
    }

    public function inquire(string $merchantTransactionId): ?string
    {
        // Skeleton: Http::...->connectTimeout(...HttpOutboundConfig::bankart('status_inquiry'))...
        return null;
    }
}
