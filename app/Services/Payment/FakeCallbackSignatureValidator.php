<?php

namespace App\Services\Payment;

use App\Contracts\CallbackSignatureValidator;
use Illuminate\Http\Request;

/**
 * Za fake bank / test – ne proverava potpis (svi callback zahtevi prolaze).
 */
class FakeCallbackSignatureValidator implements CallbackSignatureValidator
{
    public function validate(Request $request): bool
    {
        return true;
    }
}
