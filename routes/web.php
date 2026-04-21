<?php

use App\Http\Controllers\Admin\LateSuccessController;
use App\Http\Controllers\Admin\ReservationActionController;
use App\Http\Controllers\Admin\ReservationListController;
use App\Http\Controllers\AdminPanel\AuthController as AdminPanelAuthController;
use App\Http\Controllers\AdminPanel\BlockingController as AdminPanelBlockingController;
use App\Http\Controllers\AdminPanel\FreeReservationController as AdminPanelFreeReservationController;
use App\Http\Controllers\AdminPanel\AnalyticsController as AdminPanelAnalyticsController;
use App\Http\Controllers\AdminPanel\InsightController as AdminPanelInsightController;
use App\Http\Controllers\AdminPanel\ReportsController as AdminPanelReportsController;
use App\Http\Controllers\AdminPanel\ReservationController as AdminPanelReservationController;
use App\Http\Controllers\AdminPanel\SettingsController as AdminPanelSettingsController;
use App\Http\Controllers\AdminPanel\WarningsController as AdminPanelWarningsController;
use App\Http\Controllers\Control\ControlAuthController;
use App\Http\Controllers\Control\ControlDashboardController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\FakeBankCompleteController;
use App\Http\Controllers\GuestReservationController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\PaymentResultController;
use App\Http\Controllers\PaymentReturnController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReservationStatusController;
use App\Http\Controllers\UserReservationController;
use App\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('panel_admin.')->group(function () {
    Route::middleware('guest:panel_admin')->group(function () {
        Route::get('login', [AdminPanelAuthController::class, 'create'])->name('login');
        Route::post('login', [AdminPanelAuthController::class, 'store'])->name('login.store');
    });
    Route::middleware(['auth:panel_admin', 'admin.panel'])->group(function () {
        Route::post('logout', [AdminPanelAuthController::class, 'destroy'])->name('logout');
        Route::get('/', [AdminPanelWarningsController::class, 'index'])->name('dashboard');
        Route::post('alerts/{alert}/transition', [AdminPanelWarningsController::class, 'transition'])->name('alerts.transition');

        Route::get('blokiranje', [AdminPanelBlockingController::class, 'index'])->name('blocking');
        Route::post('blokiranje', [AdminPanelBlockingController::class, 'applyBlock'])->name('blocking.apply');
        Route::get('blokiranje/dan/{date}', [AdminPanelBlockingController::class, 'day'])->name('blocking.day');
        Route::post('blokiranje/dan/apply', [AdminPanelBlockingController::class, 'applyUnblock'])->name('blocking.unblock.apply');
        Route::get('blokiranje/worklist/{row}/prilagodi', [AdminPanelBlockingController::class, 'adjust'])->name('blocking.worklist.adjust');
        Route::post('blokiranje/worklist/{row}/prilagodi', [AdminPanelBlockingController::class, 'applyAdjust'])->name('blocking.worklist.adjust.apply');

        Route::get('besplatne-rezervacije', [AdminPanelFreeReservationController::class, 'create'])->name('free-reservations');
        Route::post('besplatne-rezervacije', [AdminPanelFreeReservationController::class, 'store'])->name('free-reservations.store');

        Route::get('rezervacije', [AdminPanelReservationController::class, 'index'])->name('reservations');
        Route::get('rezervacije/{reservation}/uredi', [AdminPanelReservationController::class, 'edit'])->name('reservations.edit');
        Route::put('rezervacije/{reservation}', [AdminPanelReservationController::class, 'update'])->name('reservations.update');
        Route::get('rezervacije/{reservation}/pdf', [AdminPanelReservationController::class, 'pdf'])->name('reservations.pdf');

        Route::get('uvid', [AdminPanelInsightController::class, 'index'])->name('insight');
        Route::get('uvid/{merchantTransactionId}', [AdminPanelInsightController::class, 'show'])->name('insight.show');

        Route::get('izvestaji', [AdminPanelReportsController::class, 'index'])->name('reports');
        Route::get('izvestaji/pdf', [AdminPanelReportsController::class, 'pdf'])->name('reports.pdf');

        Route::get('podesavanja', [AdminPanelSettingsController::class, 'index'])->name('settings');
        Route::put('podesavanja/capacity', [AdminPanelSettingsController::class, 'updateCapacity'])->name('settings.capacity.update');
        Route::post('podesavanja/report-emails', [AdminPanelSettingsController::class, 'storeReportEmail'])->name('settings.report-emails.store');
        Route::delete('podesavanja/report-emails/{reportEmail}', [AdminPanelSettingsController::class, 'destroyReportEmail'])->name('settings.report-emails.destroy');

        Route::get('analitika', [AdminPanelAnalyticsController::class, 'index'])->name('analytics');
        Route::get('analitika/pdf', [AdminPanelAnalyticsController::class, 'pdf'])->name('analytics.pdf');
    });
});

Route::prefix('control')->name('control.')->group(function () {
    Route::middleware('guest:control')->group(function () {
        Route::get('login', [ControlAuthController::class, 'create'])->name('login');
        Route::post('login', [ControlAuthController::class, 'store'])->name('login.store');
    });
    Route::middleware('auth:control')->group(function () {
        Route::post('logout', [ControlAuthController::class, 'destroy'])->name('logout');
        Route::get('/', [ControlDashboardController::class, 'index'])->name('dashboard');
    });
});

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

    return redirect('/guest/reserve'.($query ? '?'.$query : ''));
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
Route::post('/payment/fake-bank/complete', [FakeBankCompleteController::class, 'completeForm'])->name('payment.fake-bank.complete');

Route::get('/dashboard', function () {
    return redirect()->route('panel.reservations');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::redirect('/profile', '/panel/user')->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified'])->prefix('panel')->name('panel.')->group(function () {
    Route::get('/reservations', [UserReservationController::class, 'index'])->name('reservations');
    Route::get('/reservations/{id}/invoice/view', [UserReservationController::class, 'showInvoice'])->name('reservations.invoice.view');
    Route::get('/reservations/{id}/invoice', [UserReservationController::class, 'downloadInvoice'])->name('reservations.invoice');
    Route::patch('/reservations/{id}/vehicle', [UserReservationController::class, 'updateVehicle'])->name('reservations.vehicle');
    Route::get('/user', [ProfileController::class, 'panel'])->name('user');
    Route::get('/vehicles', [VehicleController::class, 'index'])->name('vehicles');
    Route::post('/vehicles', [VehicleController::class, 'store'])->name('vehicles.store');
    Route::patch('/vehicles/{vehicle}', [VehicleController::class, 'update'])->name('vehicles.update');
    Route::delete('/vehicles/{vehicle}', [VehicleController::class, 'destroy'])->name('vehicles.destroy');
    Route::get('/upcoming', [PanelController::class, 'upcoming'])->name('upcoming');
    Route::get('/realized', [PanelController::class, 'realized'])->name('realized');
    Route::get('/statistics', [PanelController::class, 'statistics'])->name('statistics');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('/profile/reservations', '/panel/reservations')->name('profile.reservations');
    Route::get('/profile/reservations/{id}/invoice', function (int $id) {
        return redirect()->route('panel.reservations.invoice', ['id' => $id]);
    })->whereNumber('id')->name('profile.reservations.invoice');
    Route::redirect('/profile/payments', '/panel/statistics')->name('profile.payments');
    Route::redirect('/profile/vehicles', '/panel/vehicles')->name('profile.vehicles.index');

    // Operativni pregled rezervacija (User admin): /staff — manual override (fiskal, račun, resolved)
    Route::prefix('staff')->name('staff.')->middleware('admin')->group(function () {
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
