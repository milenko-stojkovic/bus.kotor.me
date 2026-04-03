<?php

/**
 * Outbound HTTP timeouts za spoljne servise (Bankart, fiskal).
 *
 * Per-endpoint: opcioni pod-ključevi (`deposit`, `receipt`, `create_session`, `status_inquiry`) —
 * ako polje nedostaje ili je null, koriste se vrednosti iz roditeljskog `bankart` / `fiscal` bloka.
 *
 * Retry politika (namerno NE na HTTP nivou za Bankart debit):
 * - Bankart create session (debit): jedan POST, bez automatskog ponavljanja — ponovni zahtev može imati nuspojave kod provajdera; retry je na nivou korisnika / poslovnog toka.
 * - Fiskal (real): jedan pokušaj deposit + receipt; ako provajder vrati grešku 58 (nema depozita), servis interno radi tačno jedan dodatni par deposit+receipt (vidi FiscalizationService) — idempotentan depozit sa Amount=0.
 * - Fiskal (fake): isti timeouti kao real radi konzistentnog ponašanja u testu.
 *
 * Laravel job retry (ShouldQueue) pokriva privremene mrežne greške posle što HTTP već fail-uje — jobovi su idempotentni gde je to eksplicitno (callback unique key, email invoice_sent_at, itd.).
 */
return [

    'bankart' => [
        'connect_timeout' => (float) env('BANKART_HTTP_CONNECT_TIMEOUT', 5),
        'timeout' => (float) env('BANKART_HTTP_TIMEOUT', 25),
        'create_session' => [
            'connect_timeout' => env('BANKART_CREATE_SESSION_HTTP_CONNECT_TIMEOUT'),
            'timeout' => env('BANKART_CREATE_SESSION_HTTP_TIMEOUT'),
        ],
        'status_inquiry' => [
            'connect_timeout' => env('BANKART_STATUS_INQUIRY_HTTP_CONNECT_TIMEOUT'),
            'timeout' => env('BANKART_STATUS_INQUIRY_HTTP_TIMEOUT'),
        ],
    ],

    'fiscal' => [
        'connect_timeout' => (float) env('FISCAL_HTTP_CONNECT_TIMEOUT', 5),
        'timeout' => (float) env('FISCAL_HTTP_TIMEOUT', 25),
        // false samo lokalno (npr. Laragon HTTPS + self-signed); u produkciji uvek true (podrazumevano).
        'verify_ssl' => filter_var(env('FISCAL_HTTP_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN),
        'deposit' => [
            'connect_timeout' => env('FISCAL_DEPOSIT_HTTP_CONNECT_TIMEOUT'),
            'timeout' => env('FISCAL_DEPOSIT_HTTP_TIMEOUT'),
        ],
        'receipt' => [
            'connect_timeout' => env('FISCAL_RECEIPT_HTTP_CONNECT_TIMEOUT'),
            'timeout' => env('FISCAL_RECEIPT_HTTP_TIMEOUT'),
        ],
    ],

];
