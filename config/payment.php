<?php

return [
    /*
    | Payment provider: 'fake' (default) | 'real'
    | Fake = FakePaymentProvider (simulacija). Real = pravi gateway (dodati kasnije).
    */
    'provider' => env('PAYMENT_PROVIDER', 'fake'),

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
    | URI callbacka za potpis (mora odgovarati onome što banka koristi pri izračunu potpisa).
    | Npr. Bankart: "/api/payment/callback" ili "/api/payments/callback" – prema dogovoru s bankom.
    */
    'callback_path' => env('PAYMENT_CALLBACK_PATH', '/api/payment/callback'),
];
