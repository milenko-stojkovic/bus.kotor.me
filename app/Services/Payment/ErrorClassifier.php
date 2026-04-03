<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Log;

class ErrorClassifier
{
    /**
     * @param  array<string, mixed>|null  $payload
     * @return array{resolution_reason: string, category: string, notify_admin: bool, user_message_key: string, retryable: bool}
     */
    public function classify(string $source, string|int|null $raw_code, ?string $raw_message = null, ?array $payload = null): array
    {
        $code = $this->normalizeCode($raw_code);
        $reason = $this->normalizeMessage($raw_message);
        $rawCodeToken = is_string($raw_code) ? strtolower(trim($raw_code)) : null;

        $result = match ($source) {
            'bankart' => $this->classifyBankart($code, $reason, $payload, $rawCodeToken),
            'fiscal' => $this->classifyFiscal($code, $reason, $payload),
            default => $this->unknown('unknown_source'),
        };

        // Strict mapping: user-facing messages must NOT depend on raw provider message.
        $result['user_message_key'] = $this->userMessageKeyForResolutionReason($result['resolution_reason']);

        Log::channel('payments')->info('Error classified', [
            'source' => $source,
            'raw_code' => $raw_code,
            'raw_message' => $this->truncate($reason, 200),
            'resolution_reason' => $result['resolution_reason'],
            'category' => $result['category'],
            'notify_admin' => $result['notify_admin'],
            'retryable' => $result['retryable'],
            'user_message_key' => $result['user_message_key'],
            'merchant_transaction_id' => is_array($payload)
                ? ($payload['merchant_transaction_id'] ?? $payload['merchantTransactionId'] ?? null)
                : null,
            'reservation_id' => is_array($payload) ? ($payload['reservation_id'] ?? null) : null,
        ]);

        return $result;
    }

    /**
     * Optional convenience method.
     */
    public function userMessageKey(string $source, string|int|null $raw_code, ?string $raw_message = null, ?array $payload = null): string
    {
        return $this->classify($source, $raw_code, $raw_message, $payload)['user_message_key'];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array{resolution_reason: string, category: string, notify_admin: bool, user_message_key: string, retryable: bool}
     */
    private function classifyBankart(?int $code, ?string $message, ?array $payload, ?string $rawCodeToken = null): array
    {
        $msg = $message !== null ? strtolower($message) : '';
        $adapterCode = $this->extractAdapterCode($payload);

        // Non-numeric error "codes" or textual errors.
        if ($rawCodeToken !== null) {
            if (in_array($rawCodeToken, ['invalid_signature', 'signature_invalid'], true)) {
                return $this->systemNoRetry('invalid_signature', 'payment_failed');
            }
            if (in_array($rawCodeToken, ['malformed_callback', 'invalid_payload', 'malformed_payload'], true)) {
                return $this->systemNoRetry('malformed_callback', 'payment_failed');
            }
        }

        if ($msg !== '') {
            if (str_contains($msg, 'signature')) {
                return $this->systemNoRetry('invalid_signature', 'payment_failed');
            }
            if (str_contains($msg, 'malformed') || str_contains($msg, 'invalid callback') || str_contains($msg, 'invalid payload')) {
                return $this->systemNoRetry('malformed_callback', 'payment_failed');
            }
        }

        // Data-driven production mapping (grouped by behavior).
        if ($code === 2005 || ($msg !== '' && str_contains($msg, 'expired'))) {
            return $this->userRetryWithCategory('transaction_expired', 'transaction_expired', 'user_abandon');
        }

        if ($code === 2002) {
            return $this->userRetryWithCategory('user_cancelled', 'payment_failed', 'user_cancel');
        }

        if ($code === 2006 || $adapterCode === 51) {
            return $this->userRetryWithCategory('insufficient_funds', 'insufficient_funds', 'user_payment');
        }

        if ($code === 2003) {
            return $this->userRetryWithCategory('authorization_declined', 'payment_declined', 'user_payment');
        }

        if (in_array($adapterCode, [14, 54, 87, 55], true)) {
            return $this->userRetryWithCategory('card_validation_error', 'payment_declined', 'user_payment');
        }

        if (
            $code === 2021 ||
            ($msg !== '' && str_contains($msg, '3ds')) ||
            in_array($this->extractAdapterCodeToken($payload), ['01', 'r', 'timeout'], true)
        ) {
            return $this->userRetryWithCategory('3ds_failed', 'payment_declined', 'user_authentication');
        }

        if (
            $code === 1002 ||
            $code === 9999 ||
            ($msg !== '' && (str_contains($msg, 'config') || str_contains($msg, 'acquirer') || str_contains($msg, 'system failure')))
        ) {
            return $this->systemRetryWithReason('system_error', 'payment_failed');
        }

        // Default: retryable (real production behavior).
        return [
            'resolution_reason' => 'unknown_gateway_error',
            'category' => 'system',
            'notify_admin' => false,
            'user_message_key' => 'payment_try_again',
            'retryable' => true,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array{resolution_reason: string, category: string, notify_admin: bool, user_message_key: string, retryable: bool}
     */
    private function classifyFiscal(?int $code, ?string $message, ?array $payload): array
    {
        $msg = $message !== null ? strtolower($message) : '';

        // Explicit timeout classification (do NOT notify admin immediately; retryable).
        if ($msg !== '' && str_contains($msg, 'timeout')) {
            return [
                'resolution_reason' => 'timeout',
                'category' => 'system',
                'notify_admin' => false,
                'user_message_key' => 'reservation_confirmed_fiscal_pending',
                'retryable' => true,
            ];
        }

        if ($code !== null && $code >= 900 && $code <= 920) {
            return $this->systemRetry('tax_server_error', 'fiscal_pending');
        }

        return match ($code) {
            78 => [
                'resolution_reason' => 'already_fiscalized',
                'category' => 'system',
                'notify_admin' => false,
                'user_message_key' => 'reservation_confirmed_invoice_later',
                'retryable' => false,
            ],
            58 => $this->systemRetry('deposit_missing', 'fiscal_pending'),
            11 => $this->systemNoRetry('validation_error', 'fiscal_pending'),
            44 => $this->systemNoRetry('vat_config_error', 'fiscal_pending'),
            500 => $this->systemRetry('provider_down', 'fiscal_pending'),
            999 => $this->systemRetry('temporary_service_down', 'fiscal_pending'),
            default => $this->systemNoRetry('unknown_fiscal_error', 'fiscal_pending'),
        };
    }

    /** @return array{resolution_reason: string, category: 'user'|'system'|'admin_review', notify_admin: bool, user_message_key: string, retryable: bool} */
    private function userRetry(string $reason, string $messageKey): array
    {
        return [
            'resolution_reason' => $reason,
            'category' => 'user',
            'notify_admin' => false,
            'user_message_key' => $messageKey,
            'retryable' => true,
        ];
    }

    /** @return array{resolution_reason: string, category: string, notify_admin: bool, user_message_key: string, retryable: bool} */
    private function userRetryWithCategory(string $reason, string $messageKey, string $category): array
    {
        return [
            'resolution_reason' => $reason,
            'category' => $category,
            'notify_admin' => false,
            'user_message_key' => $messageKey,
            'retryable' => true,
        ];
    }

    /** @return array{resolution_reason: string, category: string, notify_admin: bool, user_message_key: string, retryable: bool} */
    private function systemRetryWithReason(string $reason, string $messageKey): array
    {
        return [
            'resolution_reason' => $reason,
            'category' => 'system',
            'notify_admin' => true,
            'user_message_key' => $messageKey,
            'retryable' => true,
        ];
    }

    /** @return array{resolution_reason: string, category: 'user'|'system'|'admin_review', notify_admin: bool, user_message_key: string, retryable: bool} */
    private function systemRetry(string $reason, string $messageKey): array
    {
        return [
            'resolution_reason' => $reason,
            'category' => 'system',
            'notify_admin' => true,
            'user_message_key' => $messageKey,
            'retryable' => true,
        ];
    }

    /** @return array{resolution_reason: string, category: 'user'|'system'|'admin_review', notify_admin: bool, user_message_key: string, retryable: bool} */
    private function systemNoRetry(string $reason, string $messageKey): array
    {
        return [
            'resolution_reason' => $reason,
            'category' => 'system',
            'notify_admin' => true,
            'user_message_key' => $messageKey,
            'retryable' => false,
        ];
    }

    /** @return array{resolution_reason: string, category: 'user'|'system'|'admin_review', notify_admin: bool, user_message_key: string, retryable: bool} */
    private function unknown(string $reason): array
    {
        return [
            'resolution_reason' => $reason,
            'category' => 'system',
            'notify_admin' => true,
            'user_message_key' => 'payment_processing_issue',
            'retryable' => false,
        ];
    }

    private function normalizeCode(string|int|null $raw): ?int
    {
        if ($raw === null) {
            return null;
        }
        if (is_int($raw)) {
            return $raw;
        }
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (! preg_match('/^-?\d+$/', $raw)) {
            return null;
        }

        return (int) $raw;
    }

    private function normalizeMessage(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $raw = trim($raw);

        return $raw === '' ? null : $raw;
    }

    private function truncate(?string $s, int $max): ?string
    {
        if ($s === null) {
            return null;
        }
        if (mb_strlen($s) <= $max) {
            return $s;
        }

        return mb_substr($s, 0, $max - 1).'…';
    }

    private function userMessageKeyForResolutionReason(string $resolutionReason): string
    {
        return match ($resolutionReason) {
            // Bankart
            'transaction_expired' => 'payment_expired',
            'user_cancelled' => 'payment_cancelled',
            'insufficient_funds' => 'payment_insufficient_funds',
            'authorization_declined' => 'payment_declined',
            'card_validation_error' => 'payment_card_check',
            '3ds_failed' => 'payment_authentication_failed',
            'system_error' => 'payment_processing_issue',
            'invalid_signature' => 'payment_processing_issue',
            'malformed_callback' => 'payment_processing_issue',
            'unknown_gateway_error' => 'payment_try_again',

            // Fiscal
            'already_fiscalized' => 'reservation_confirmed_invoice_later',
            'deposit_missing' => 'reservation_confirmed_fiscal_pending',
            'validation_error' => 'reservation_confirmed_fiscal_pending',
            'vat_config_error' => 'reservation_confirmed_fiscal_pending',
            'provider_down' => 'reservation_confirmed_fiscal_pending',
            'tax_server_error' => 'reservation_confirmed_fiscal_pending',
            'temporary_service_down' => 'reservation_confirmed_fiscal_pending',
            'timeout' => 'reservation_confirmed_fiscal_pending',
            'unknown_fiscal_error' => 'reservation_confirmed_fiscal_pending',

            default => 'payment_processing_issue',
        };
    }

    /**
     * Extract adapterCode from bank payload, if present.
     *
     * @param  array<string, mixed>|null  $payload
     */
    private function extractAdapterCode(?array $payload): ?int
    {
        if (! is_array($payload)) {
            return null;
        }

        $raw = $payload['adapterCode']
            ?? $payload['adapter_code']
            ?? ($payload['adapter'] ?? null);

        if (is_int($raw)) {
            return $raw;
        }
        if (! is_string($raw)) {
            return null;
        }
        $raw = trim($raw);
        if ($raw === '' || ! preg_match('/^-?\d+$/', $raw)) {
            return null;
        }

        return (int) $raw;
    }

    /**
     * Extract adapterCode token if it's non-numeric (e.g. "R", "timeout", "01").
     *
     * @param  array<string, mixed>|null  $payload
     */
    private function extractAdapterCodeToken(?array $payload): ?string
    {
        if (! is_array($payload)) {
            return null;
        }

        $raw = $payload['adapterCode'] ?? $payload['adapter_code'] ?? null;
        if (! is_string($raw)) {
            return null;
        }
        $raw = strtolower(trim($raw));

        return $raw === '' ? null : $raw;
    }
}

