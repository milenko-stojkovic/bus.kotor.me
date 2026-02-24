<?php

namespace App\Contracts;

use App\Models\TempData;

/**
 * Payment service – provider iza interfejsa (fake | real).
 *
 * Redirect flow:
 * - Controller (sync): createSession() → payment_url ili error; nikad obrada rezultata u HTTP.
 * - Rezultat plaćanja: webhook/callback → queue job (rezervacija, fiskalizacija, email).
 */
interface PaymentService
{
    /**
     * Kreira payment session kod gatewaya (sync). Za redirect UX – odmah nakon "Pay".
     * Vraća payment_url za redirect ili error ako je gateway spor/nedostupan.
     */
    public function createSession(TempData $tempData): PaymentSessionResult;

    /**
     * Pokreni plaćanje za temp_data (koristi se iz PaymentCallbackJob nakon webhook-a).
     * Vraća success → kreiraj rezervaciju; failed → status failed; timeout → late_success.
     */
    public function pay(TempData $tempData): PaymentResult;
}
