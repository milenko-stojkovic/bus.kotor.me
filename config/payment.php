<?php

return [
    /*
    | Payment provider: 'fake' (default) | 'real'
    | Fake = FakePaymentProvider (simulacija). Real = pravi gateway (dodati kasnije).
    */
    'provider' => env('PAYMENT_PROVIDER', 'fake'),

    /*
    | Callback signing secret (real gateway). Koristi se za validaciju potpisa webhook/callback zahteva.
    | Ako nije setovan a provider = real, callback se odbija.
    */
    'callback_secret' => env('PAYMENT_CALLBACK_SECRET'),

    /*
    | Pending inquiry: temp_data pending starije od ovog broja minuta proveravaju se kod banke (status inquiry).
    | Ako banka kaže SUCCESS → pokreće se isti flow kao callback. Timeout callback-a (callback nikad ne stigne).
    */
    'pending_inquiry_after_minutes' => (int) env('PAYMENT_PENDING_INQUIRY_AFTER_MINUTES', 10),
];
