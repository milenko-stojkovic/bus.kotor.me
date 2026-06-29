<?php

namespace App\Services\Payment;

use App\Models\TempData;
use App\Services\AdminPanel\AdminAlertService;
use App\Support\BankartBillingCountry;
use App\Support\UiText;
use Illuminate\Support\Facades\Log;

final class BankartBillingCountryAlertService
{
    public const TYPE = 'bankart_billing_country_invalid';

    /**
     * @param  array<string, mixed>  $extra
     */
    public function notifyAndLog(
        string $rawCountry,
        ?string $normalizedCountry,
        ?int $userId = null,
        ?string $email = null,
        ?string $reservationKind = null,
        ?string $reservationDate = null,
        ?string $merchantTransactionId = null,
        ?int $tempDataId = null,
        string $stage = 'checkout',
        array $extra = [],
    ): void {
        $context = array_merge([
            'stage' => $stage,
            'user_id' => $userId,
            'email' => $email,
            'selected_country' => $rawCountry,
            'normalized_country' => $normalizedCountry,
            'reservation_kind' => $reservationKind,
            'reservation_date' => $reservationDate,
            'merchant_transaction_id' => $merchantTransactionId,
            'temp_data_id' => $tempDataId,
            'guest' => $userId === null,
        ], $extra);

        Log::channel('payments')->warning('checkout_billing_country_invalid', array_filter(
            $context,
            static fn ($v) => $v !== null && $v !== '',
        ));

        $dedupeKey = 'bankart_billing_country_invalid:'.($userId !== null ? 'user:'.$userId : 'email:'.($email ?? 'unknown'));

        app(AdminAlertService::class)->createOnce(
            self::TYPE,
            'Bankart: neispravna država za naplatu',
            sprintf(
                'Plaćanje nije pokrenuto — billingCountry nije validan ISO alpha-2 (vrijednost: %s). Korisnik: %s (id: %s).',
                $rawCountry !== '' ? $rawCountry : '—',
                $email ?? '—',
                $userId !== null ? (string) $userId : 'guest',
            ),
            'medium',
            $dedupeKey,
            $context,
        );
    }

    public function userMessage(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return UiText::t(
            'payment',
            'billing_country_invalid',
            $locale === 'cg'
                ? 'Država za naplatu nije ispravno podešena. Molimo kontaktirajte podršku na bus@kotor.me.'
                : 'Billing country is not configured correctly. Please contact support at bus@kotor.me.',
            $locale,
        );
    }

    public function notifyForTempData(TempData $temp, string $stage = 'checkout'): void
    {
        $this->notifyAndLog(
            (string) ($temp->country ?? ''),
            BankartBillingCountry::normalize($temp->country),
            $temp->user_id !== null ? (int) $temp->user_id : null,
            $temp->email,
            $temp->reservation_kind,
            $temp->reservation_date?->format('Y-m-d'),
            $temp->merchant_transaction_id,
            (int) $temp->id,
            $stage,
        );
    }
}
