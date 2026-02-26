<?php

use App\Http\Controllers\Api\PaymentCallbackController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Bank callback – API only. No web middleware (no session, no CSRF, no redirects).
| Stateless; returns only 200/202/400. User redirect via frontend polling /payment/result.
| Security: rate-limited (60/min); every payload logged (minimal always, full in debug).
|--------------------------------------------------------------------------
*/

Route::post('payments/callback', [PaymentCallbackController::class, 'handle'])
    ->name('api.payment.callback')
    ->middleware('throttle:60,1');
