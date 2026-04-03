<?php

namespace App\Providers;

use App\Contracts\CallbackSignatureValidator;
use App\Contracts\PaymentService;
use App\Contracts\PaymentStatusInquiryService;
use App\Events\PaymentFailed;
use App\Listeners\LogPaymentFailed;
use App\Listeners\NotifyUserPaymentFailed;
use App\Services\Payment\FakeCallbackSignatureValidator;
use App\Services\Payment\FakePaymentProvider;
use App\Services\Payment\FakePaymentStatusInquiryService;
use App\Services\Payment\RealCallbackSignatureValidator;
use App\Services\Payment\RealPaymentProvider;
use App\Services\Payment\RealPaymentStatusInquiryService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $bankDriver = config('services.bank.driver') ?? config('payment.provider', 'fake');
        $this->app->bind(PaymentService::class, function () use ($bankDriver) {
            return match ($bankDriver) {
                'bankart' => $this->app->make(RealPaymentProvider::class),
                default => $this->app->make(FakePaymentProvider::class),
            };
        });

        $this->app->bind(CallbackSignatureValidator::class, function () use ($bankDriver) {
            return match ($bankDriver) {
                'bankart' => $this->app->make(RealCallbackSignatureValidator::class),
                default => $this->app->make(FakeCallbackSignatureValidator::class),
            };
        });

        $this->app->bind(PaymentStatusInquiryService::class, function () use ($bankDriver) {
            return match ($bankDriver) {
                'bankart' => $this->app->make(RealPaymentStatusInquiryService::class),
                default => $this->app->make(FakePaymentStatusInquiryService::class),
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

        if ($this->app->environment('production')) {
            $bank = (string) config('services.bank.driver', 'fake');
            $fiscal = (string) config('services.fiscalization.driver', 'fake');
            if ($bank === 'fake' || $fiscal === 'fake') {
                Cache::remember('bootstrap_warn_fake_payment_drivers', 43_200, function () use ($bank, $fiscal): bool {
                    Log::channel('payments')->warning('production_fake_driver_active', [
                        'bank_driver' => $bank,
                        'fiscalization_driver' => $fiscal,
                        'hint' => 'Production should use BANK_DRIVER=bankart and FISCALIZATION_DRIVER=real.',
                    ]);

                    return true;
                });
            }
        }
    }
}
