<?php

use App\Http\Controllers\Admin\ReservationActionController;
use App\Http\Controllers\Admin\LateSuccessController;
use App\Http\Controllers\Admin\ReservationListController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\GuestReservationController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\UserReservationController;
use App\Http\Controllers\FakeBankCompleteController;
use App\Http\Controllers\FakeFiscalScenarioController;
use App\Http\Controllers\PaymentReturnController;
use App\Http\Controllers\PaymentResultController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReservationStatusController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\UserPaymentHistoryController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('landing');
Route::get('/guest/reserve', GuestReservationController::class)->name('guest.reserve');

// Guest: manually change UI language (session). Auth uses users.lang.
Route::get('/locale/{locale}', LocaleController::class)->name('locale.switch');

// Checkout: validacija, dostupnost, temp_data (pending), soft-lock, createSession (sync), redirect na payment_url ili 503
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

// Guest nakon failed plaćanja: /reservations?retry_token=... → redirect na /guest/reserve sa query.
// Frontend na /guest/reserve: ako je retry_token u URL, pozvati GET /api/reservations/retry/{token} i popuniti formu;
// prikazati session('message') i session('error_reason') ako postoje.
Route::get('/reservations', function () {
    $query = request()->getQueryString();
    return redirect('/guest/reserve' . ($query ? '?' . $query : ''));
})->name('reservations.create');

// Polling endpoint za status rezervacije (UI periodično poziva sa merchant_transaction_id)
Route::get('/reservation-status/{merchant_transaction_id}', [ReservationStatusController::class, 'show'])->name('reservation.status');

// Stranica na koju korisnik stiže nakon redirecta sa banke. Status uvek iz baze (UI nije izvor istine).
Route::get('/payment/return', PaymentReturnController::class)->name('payment.return');
// API za status: GET /payment/result?merchant_transaction_id=... → JSON { status, user_type, message?, ... }
Route::get('/payment/result', PaymentResultController::class)->name('payment.result');

// Bank callback = POST /api/payment/callback (routes/api.php). Machine-to-machine ONLY. Frontend NIKAD ne sme da ga poziva.

// Fake bank (samo test): stranica + poseban completion endpoint. Frontend poziva completion, NE bank callback.
Route::get('/payment/fake-bank', function (\Illuminate\Http\Request $request) {
    $tx = $request->query('tx');
    if (! $tx) {
        return redirect('/')->with('error', 'Missing transaction id');
    }
    return view('payment.fake-bank', ['merchant_transaction_id' => $tx]);
})->name('payment.fake-bank');
Route::get('/fake-bank/complete', [FakeBankCompleteController::class, 'completeGet'])->name('fake-bank.complete');
Route::post('/payment/fake-bank/complete', FakeBankCompleteController::class)->name('payment.fake-bank.complete');

// Fake fiscal scenario selector (local only)
Route::get('/payment/fake-fiscal', [FakeFiscalScenarioController::class, 'index'])->name('payment.fake-fiscal');
Route::post('/payment/fake-fiscal/apply', [FakeFiscalScenarioController::class, 'apply'])->name('payment.fake-fiscal.apply');
Route::post('/payment/fake-fiscal/set', [FakeFiscalScenarioController::class, 'set'])->name('payment.fake-fiscal.set');
Route::post('/payment/fake-fiscal/clear', [FakeFiscalScenarioController::class, 'clear'])->name('payment.fake-fiscal.clear');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/profile/reservations', [UserReservationController::class, 'index'])->name('profile.reservations');
    Route::get('/profile/reservations/{id}/invoice', [UserReservationController::class, 'downloadInvoice'])->name('profile.reservations.invoice');
    Route::get('/profile/payments', UserPaymentHistoryController::class)->name('profile.payments');
    Route::prefix('/profile/vehicles')->name('profile.vehicles.')->group(function () {
        Route::get('/', [VehicleController::class, 'index'])->name('index');
        Route::post('/', [VehicleController::class, 'store'])->name('store');
        Route::patch('/{vehicle}', [VehicleController::class, 'update'])->name('update');
        Route::delete('/{vehicle}', [VehicleController::class, 'destroy'])->name('destroy');
    });

    // Admin panel: pregled rezervacija + manual override (retry fiscalization, resend invoice, mark resolved)
    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
        Route::get('/reservations', [ReservationListController::class, 'index'])->name('reservations.index');
        Route::post('/reservations/{id}/retry-fiscalization', [ReservationActionController::class, 'retryFiscalization'])->name('reservations.retry-fiscalization');
        Route::post('/reservations/{id}/resend-invoice', [ReservationActionController::class, 'resendInvoice'])->name('reservations.resend-invoice');
        Route::post('/reservations/{id}/mark-resolved', [ReservationActionController::class, 'markResolved'])->name('reservations.mark-resolved');

        Route::prefix('late-success')->name('late-success.')->group(function () {
            Route::get('/', [LateSuccessController::class, 'index'])->name('index');
            Route::get('/{id}', [LateSuccessController::class, 'show'])->name('show');
            Route::post('/{id}/force', [LateSuccessController::class, 'forceCreate'])->name('force');
            Route::post('/{id}/reject', [LateSuccessController::class, 'reject'])->name('reject');
        });
    });
});

require __DIR__.'/auth.php';
