<?php

namespace App\Support;

/**
 * Centralna odluka sync vs queue za fake bank + fake fiskal QA ({@see config('payment.fake_e2e_sync')} / FAKE_PAYMENT_E2E_SYNC).
 *
 * Terminologija (oba drivera fake):
 * - **Fake QA queue režim:** `QUEUE_CONNECTION=database` (ili redis), `FAKE_PAYMENT_E2E_SYNC=false` → pipeline jobovi
 *   (`ProcessReservationAfterPaymentJob`, `SendInvoiceEmailJob` gdje se koristi ovaj helper) idu {@see dispatch()} → potreban worker.
 * - **Fake QA sync režim:** `FAKE_PAYMENT_E2E_SYNC=true` → isti pipeline ide `dispatch_sync`; worker nije bitan za taj tok.
 *
 * `QUEUE_CONNECTION=database` samo omogućava red; ne garantuje ga ako se negdje koristi `dispatch_sync` (ovaj helper kad je sync režim,
 * ili {@see dispatchPaymentCallbackSyncForFakeQaForm} za fake-bank formu — callback uvijek inline).
 *
 * Real bankart / real fiskal: {@see useSyncForFake()} je uvijek false; koristi se običan {@see dispatch()}.
 */
final class QueueMode
{
    /**
     * true samo kad su BANK_DRIVER fake, FISCALIZATION_DRIVER fake i FAKE_PAYMENT_E2E_SYNC=true.
     */
    public static function useSyncForFake(): bool
    {
        if (! filter_var(config('payment.fake_e2e_sync'), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $bankFake = (config('services.bank.driver') ?? config('payment.provider', 'fake')) === 'fake';
        $fiscalFake = config('services.fiscalization.driver') === 'fake';

        return $bankFake && $fiscalFake;
    }

    /**
     * @param  object  $job  Job instanca (ShouldQueue); ne koristiti za produkcijske real tokove — tamo direktno Job::dispatch.
     */
    public static function dispatchForFakeE2e(object $job): void
    {
        if (self::useSyncForFake()) {
            dispatch_sync($job);
        } else {
            dispatch($job);
        }
    }

    /**
     * Fake-bank QA forma: callback job mora uvijek sync u istom HTTP zahtjevu prije {@see FakeBankCompleteController::runDeferredFakeFiscalPipeline()}.
     */
    public static function dispatchPaymentCallbackSyncForFakeQaForm(object $job): void
    {
        dispatch_sync($job);
    }
}
