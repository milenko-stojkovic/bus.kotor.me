<?php

return [
    /*
    | Payment provider: 'fake' (default) | 'real'
    | Fake = FakePaymentProvider (simulacija). Real = pravi gateway (dodati kasnije).
    */
    'provider' => env('PAYMENT_PROVIDER', 'fake'),

    /*
    | Kada je BANK_DRIVER=fake: pokreni callback + fiskal + PDF + email sinhrono (isti HTTP zahtjev kao
    | fake-bank complete), bez obaveznog queue workera. Isključi (false) ako namerno koristiš database/redis
    | queue i php artisan queue:work za test.
    */
    'fake_e2e_sync' => filter_var(env('FAKE_PAYMENT_E2E_SYNC', true), FILTER_VALIDATE_BOOLEAN),

    /*
    | Callback signing secret (real gateway). Koristi se za validaciju potpisa webhook/callback zahteva.
    | Bankart: isti secret kao za HMAC potpis = BANKART_SHARED_SECRET. Fallback na PAYMENT_CALLBACK_SECRET.
    */
    'callback_secret' => env('BANKART_SHARED_SECRET') ?? env('PAYMENT_CALLBACK_SECRET'),

    /*
    | Pending inquiry: temp_data pending starije od ovog broja minuta proveravaju se kod banke (status inquiry).
    | Ako banka kaže SUCCESS → pokreće se isti flow kao callback. Timeout callback-a (callback nikad ne stigne).
    */
    'pending_inquiry_after_minutes' => (int) env('PAYMENT_PENDING_INQUIRY_AFTER_MINUTES', 10),

    /*
    | temp_data u pending duže od ovoga: log WARNING `payment_pending_too_long` (bez promene statusa).
    | Throttle u kešu po temp_data id da log ne flood-uje scheduler.
    */
    'stale_pending_warn_after_minutes' => (int) env('PAYMENT_STALE_PENDING_WARN_AFTER_MINUTES', 12),

    /*
    | URI callbacka za potpis (mora odgovarati onome što banka koristi pri izračunu potpisa).
    | U ovom projektu: POST /api/payment/callback (routes/api.php). Banka mora biti podešena na isti path.
    */
    'callback_path' => env('PAYMENT_CALLBACK_PATH', '/api/payment/callback'),
];
