<?php

namespace App\Services\Payment;

use App\Contracts\CallbackSignatureValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Validacija potpisa callback-a od pravog gatewaya (npr. Bankart).
 * Dok nije implementirano: vraća false ako nije podešen secret; kasnije HMAC nad telom zahteva.
 */
class RealCallbackSignatureValidator implements CallbackSignatureValidator
{
    public function validate(Request $request): bool
    {
        $secret = config('payment.callback_secret');
        if (empty($secret)) {
            Log::channel('payments')->warning('Payment callback: PAYMENT_CALLBACK_SECRET not set, rejecting callback.');

            return false;
        }

        // TODO: implementirati stvarnu proveru potpisa (npr. Bankart HMAC nad raw body + header)
        // Primer: $signature = $request->header('X-Signature'); return hash_equals($expected, $signature);
        Log::channel('payments')->warning('Real callback signature validation not implemented, rejecting callback.');

        return false;
    }
}
