<?php

namespace App\Services\Payment;

use App\Contracts\PaymentStatusInquiryService;
use Illuminate\Support\Facades\Log;

/**
 * Pravi gateway – status inquiry API. Ako banka kaže SUCCESS, cron pokreće isti flow kao callback.
 * TODO: HTTP poziv ka bankinom status inquiry endpoint-u; parsiranje odgovora (success/failed).
 */
class RealPaymentStatusInquiryService implements PaymentStatusInquiryService
{
    public function inquire(string $merchantTransactionId): ?string
    {
        // TODO: call bank status inquiry endpoint, return 'success' | 'failed' | null
        Log::channel('payments')->debug('Payment status inquiry not implemented', [
            'merchant_transaction_id' => $merchantTransactionId,
        ]);

        return null;
    }
}
