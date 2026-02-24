<?php

namespace App\Providers;

use App\Contracts\CallbackSignatureValidator;
use App\Contracts\PaymentService;
use App\Events\PaymentFailed;
use App\Listeners\LogPaymentFailed;
use App\Listeners\NotifyUserPaymentFailed;
use App\Services\Payment\FakeCallbackSignatureValidator;
use App\Services\Payment\FakePaymentProvider;
use App\Services\Payment\RealCallbackSignatureValidator;
use App\Services\Payment\RealPaymentProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentService::class, function () {
            return match (config('payment.provider', 'fake')) {
                'real' => $this->app->make(RealPaymentProvider::class),
                default => $this->app->make(FakePaymentProvider::class),
            };
        });

        $this->app->bind(CallbackSignatureValidator::class, function () {
            return match (config('payment.provider', 'fake')) {
                'real' => $this->app->make(RealCallbackSignatureValidator::class),
                default => $this->app->make(FakeCallbackSignatureValidator::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(PaymentFailed::class, LogPaymentFailed::class);
        Event::listen(PaymentFailed::class, NotifyUserPaymentFailed::class);
    }
}
