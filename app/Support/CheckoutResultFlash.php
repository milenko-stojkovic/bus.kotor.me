<?php

namespace App\Support;

/**
 * Maps checkout/payment outcomes to session flash banners (group checkout_result, UiText).
 *
 * @phpstan-type Banner array{level: string, title_key: string, message_key: string, group: string}
 */
final class CheckoutResultFlash
{
    public const GROUP = 'checkout_result';

    /**
     * Paid or free reservation after payment pipeline (reservation row exists).
     *
     * @return Banner
     */
    public static function forReservationSuccess(bool $isFreeReservation, bool $fiscalComplete): array
    {
        if ($isFreeReservation) {
            return self::wrap('success', 'free_success_title', 'free_success_message');
        }

        if ($fiscalComplete) {
            return self::wrap('success', 'paid_success_title', 'paid_success_message');
        }

        return self::wrap('info', 'fiscal_delayed_title', 'fiscal_delayed_message');
    }

    /**
     * Failed/cancelled payment (temp_data terminal, no reservation).
     * Expect {@see PaymentResultResolver} to pass a normalized `resolution_reason` when possible.
     *
     * @return Banner
     */
    public static function forPaymentFailure(?string $resolutionReason): array
    {
        $r = is_string($resolutionReason) ? trim($resolutionReason) : '';
        if ($r === '') {
            $r = 'unknown_gateway_error';
        }

        return match ($r) {
            'transaction_expired' => self::wrap('error', 'payment_expired_title', 'payment_expired_message'),
            'user_cancelled' => self::wrap('error', 'payment_cancelled_title', 'payment_cancelled_message'),
            'insufficient_funds' => self::wrap('error', 'payment_insufficient_funds_title', 'payment_insufficient_funds_message'),
            'authorization_declined' => self::wrap('error', 'payment_declined_title', 'payment_declined_message'),
            '3ds_failed' => self::wrap('error', 'payment_3ds_failed_title', 'payment_3ds_failed_message'),
            'card_validation_error' => self::wrap('error', 'payment_card_validation_title', 'payment_card_validation_message'),
            'invalid_signature', 'malformed_callback' => self::wrap('error', 'payment_unconfirmed_title', 'payment_unconfirmed_message'),
            'system_error', 'unknown_gateway_error' => self::wrap('error', 'payment_system_error_title', 'payment_system_error_message'),
            default => self::wrap('error', 'payment_system_error_title', 'payment_system_error_message'),
        };
    }

    /**
     * @return Banner
     */
    public static function lateSuccess(): array
    {
        return self::wrap('info', 'late_success_title', 'late_success_message');
    }

    /**
     * Missing/invalid return URL parameters or unknown transaction.
     *
     * @return Banner
     */
    public static function invalidPaymentReturn(): array
    {
        return self::wrap('error', 'payment_unconfirmed_title', 'payment_unconfirmed_message');
    }

    /**
     * @return Banner
     */
    public static function freeReservationFailed(): array
    {
        return self::wrap('error', 'free_failed_title', 'free_failed_message');
    }

    /**
     * @return Banner
     */
    private static function wrap(string $level, string $titleKey, string $messageKey): array
    {
        return [
            'level' => $level,
            'title_key' => $titleKey,
            'message_key' => $messageKey,
            'group' => self::GROUP,
        ];
    }
}
