<?php

namespace App\Listeners;

use App\Events\PaymentFailed;
use Illuminate\Support\Facades\Log;

/**
 * Loguje CANCEL/ERROR u payments channel (production safety, monitoring).
 */
class LogPaymentFailed
{
    public function handle(PaymentFailed $event): void
    {
        $temp = $event->tempData;
        Log::channel('payments')->info('Payment failed or cancelled', [
            'merchant_transaction_id' => $temp->merchant_transaction_id,
            'temp_data_id' => $temp->id,
            'user_id' => $temp->user_id,
            'reservation_id' => null,
            'callback_error_code' => $temp->callback_error_code,
            'callback_error_reason' => $temp->callback_error_reason,
        ]);
    }
}
