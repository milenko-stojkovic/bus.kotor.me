<?php

namespace App\Services\Payment;

use App\Contracts\CallbackSignatureValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Validacija potpisa callback-a od pravog gatewaya (npr. Bankart).
 *
 * Namjerno (V1): vraća false dok se ne implementira HMAC prema specifikaciji banke.
 * Svi callbacki su odbijeni kada je provider=real; za test koristiti provider=fake.
 * V. docs/payment-v1-production-audit.md – sekcija „Namjerna odstupanja“.
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

        // TODO: implementirati stvarnu provjeru potpisa (HMAC nad raw body + header prema spec banke)
        Log::channel('payments')->warning('Real callback signature validation not implemented, rejecting callback.');

        return false;
    }
}
