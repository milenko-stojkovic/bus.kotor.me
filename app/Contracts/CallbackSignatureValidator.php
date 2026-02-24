<?php

namespace App\Contracts;

use Illuminate\Http\Request;

/**
 * Validacija potpisa callback-a od payment gatewaya (npr. Bankart).
 * Callback endpoint mora validirati potpis pre dispatch-a job-a; neobrazovani zahtevi se odbijaju (401).
 */
interface CallbackSignatureValidator
{
    /**
     * Proveri da li je potpis u request-u validan (HMAC / signing key od gatewaya).
     * Ako false – callback controller ne sme dispatch-ovati job.
     */
    public function validate(Request $request): bool;
}
