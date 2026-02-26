<?php

use App\Http\Controllers\Admin\ReservationActionController;
use App\Http\Controllers\Admin\ReservationListController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\FakeBankCompleteController;
use App\Http\Controllers\PaymentReturnController;
use App\Http\Controllers\PaymentResultController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReservationStatusController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Guest: manually change UI language (session). Auth uses users.lang.
Route::get('/locale/{locale}', LocaleController::class)->name('locale.switch');

// Checkout: validacija, dostupnost, temp_data (pending), soft-lock, createSession (sync), redirect na payment_url ili 503
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

// Guest nakon failed plaćanja: /reservations?retry_token=... → redirect na / sa query.
// Frontend na /: ako je retry_token u URL, pozvati GET /api/reservations/retry/{token} i popuniti formu;
// prikazati session('message') i session('error_reason') ako postoje.
Route::get('/reservations', function () {
    $query = request()->getQueryString();
    return redirect('/' . ($query ? '?' . $query : ''));
})->name('reservations.create');

// Polling endpoint za status rezervacije (UI periodično poziva sa merchant_transaction_id)
Route::get('/reservation-status/{merchant_transaction_id}', [ReservationStatusController::class, 'show'])->name('reservation.status');

// Stranica na koju korisnik stiže nakon redirecta sa banke. Status uvek iz baze (UI nije izvor istine).
Route::get('/payment/return', PaymentReturnController::class)->name('payment.return');
// API za status: GET /payment/result?merchant_transaction_id=... → JSON { status, user_type, message?, ... }
Route::get('/payment/result', PaymentResultController::class)->name('payment.result');

// Bank callback = POST /api/payments/callback (routes/api.php). Machine-to-machine ONLY. Frontend NIKAD ne sme da ga poziva.

// Fake bank (samo test): stranica + poseban completion endpoint. Frontend poziva completion, NE bank callback.
Route::get('/payment/fake-bank', function (\Illuminate\Http\Request $request) {
    $tx = $request->query('tx');
    if (! $tx) {
        return redirect('/')->with('error', 'Missing transaction id');
    }
    return view('payment.fake-bank', ['merchant_transaction_id' => $tx]);
})->name('payment.fake-bank');
Route::post('/payment/fake-bank/complete', FakeBankCompleteController::class)->name('payment.fake-bank.complete');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Pregled rezervacija korisnika (auth redirect nakon failed plaćanja)
    Route::get('/profile/reservations', function () {
        return redirect()->route('dashboard')->with('error', __('Plaćanje nije uspelo.'));
    })->name('profile.reservations');

    // Admin panel: pregled rezervacija + manual override (retry fiscalization, resend invoice, mark resolved)
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/reservations', [ReservationListController::class, 'index'])->name('reservations.index');
        Route::post('/reservations/{id}/retry-fiscalization', [ReservationActionController::class, 'retryFiscalization'])->name('reservations.retry-fiscalization');
        Route::post('/reservations/{id}/resend-invoice', [ReservationActionController::class, 'resendInvoice'])->name('reservations.resend-invoice');
        Route::post('/reservations/{id}/mark-resolved', [ReservationActionController::class, 'markResolved'])->name('reservations.mark-resolved');
    });
});

require __DIR__.'/auth.php';
