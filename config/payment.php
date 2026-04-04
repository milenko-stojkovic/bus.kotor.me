<?php

return [
    /*
    | Payment provider: 'fake' (default) | 'real'
    | Fake = FakePaymentProvider (simulacija). Real = pravi gateway (dodati kasnije).
    */
    'provider' => env('PAYMENT_PROVIDER', 'fake'),

    /*
    | Bankart: uključi HTTP status inquiry u cron-u (GET getByMerchantTransactionId). Isključi ako provajder još nije spreman.
    */
    'bankart_status_inquiry_enabled' => filter_var(env('BANKART_STATUS_INQUIRY_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    /*
    | Prekidač za fake QA pipeline (oba drivera fake): sync vs queue. Ne utiče na real bankart/fiskal.
    |
    | - true  = fake QA sync režim: {@see \App\Support\QueueMode::dispatchForFakeE2e} → dispatch_sync za pipeline jobove;
    |           worker nije bitan za taj happy path (QUEUE_CONNECTION može biti database — i dalje se koristi sync za te dispatche).
    | - false = fake QA queue režim (uz QUEUE_CONNECTION=database|redis): isti jobovi idu na red → potreban queue:work.
    |
    | QUEUE_CONNECTION=database samo omogućava red; ne garantuje ga ako kod pozove dispatch_sync (npr. ovaj flag true ili inline callback na formi).
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
    | Minimalni interval između dva status inquiry poziva za isti merchant_transaction_id (keš).
    */
    'status_inquiry_throttle_minutes' => (int) env('PAYMENT_STATUS_INQUIRY_THROTTLE_MINUTES', 20),

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
