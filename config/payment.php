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
];
