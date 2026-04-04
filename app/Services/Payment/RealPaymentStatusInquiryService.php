<?php

namespace App\Services\Payment;

use App\Contracts\PaymentStatusInquiryService;
use App\Support\BankartSignature;
use App\Support\HttpOutboundConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bankart API v3: GET /status/{apiKey}/getByMerchantTransactionId/{merchantTransactionId}.
 * Isti Basic auth i X-Signature model kao {@see RealPaymentProvider} (GET, prazno telo, prazan Content-Type u potpisu).
 */
class RealPaymentStatusInquiryService implements PaymentStatusInquiryService
{
    public function isImplemented(): bool
    {
        if (! filter_var(config('payment.bankart_status_inquiry_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $cfg = config('services.bankart');
        $apiBase = rtrim((string) ($cfg['api_url'] ?? ''), '/');
        $apiKey = (string) ($cfg['api_key'] ?? '');
        $username = (string) ($cfg['username'] ?? '');
        $password = (string) ($cfg['password'] ?? '');
        $sharedSecret = (string) ($cfg['shared_secret'] ?? '');
        $signatureEnabled = (bool) ($cfg['signature_enabled'] ?? true);

        return $apiBase !== '' && $apiKey !== '' && $username !== '' && $password !== ''
            && (! $signatureEnabled || $sharedSecret !== '');
    }

    public function inquire(string $merchantTransactionId): array
    {
        $merchantTransactionId = trim($merchantTransactionId);
        $empty = ['outcome' => null, 'raw' => []];

        if ($merchantTransactionId === '') {
            return $empty;
        }

        $cfg = config('services.bankart');
        $apiBase = rtrim((string) ($cfg['api_url'] ?? ''), '/');
        $apiKey = (string) ($cfg['api_key'] ?? '');
        $username = (string) ($cfg['username'] ?? '');
        $password = (string) ($cfg['password'] ?? '');
        $sharedSecret = (string) ($cfg['shared_secret'] ?? '');
        $signatureEnabled = (bool) ($cfg['signature_enabled'] ?? true);

        $path = '/status/'.$apiKey.'/getByMerchantTransactionId/'.rawurlencode($merchantTransactionId);
        $url = $apiBase.$path;
        $signaturePath = BankartSignature::resolveSignaturePath($apiBase, $path);
        $date = gmdate('D, d M Y H:i:s').' GMT';
        $rawBody = '';
        $contentType = '';

        Log::channel('payments')->info('payment_status_inquiry_started', [
            'merchant_transaction_id' => $merchantTransactionId,
            'signature_path' => $signaturePath,
        ]);

        $headers = [
            'Accept' => 'application/json',
        ];

        if ($signatureEnabled) {
            $sig = BankartSignature::sign('GET', $rawBody, $contentType, $date, $signaturePath, $sharedSecret);
            if ($sig === null) {
                Log::channel('payments')->error('payment_status_inquiry_signing_failed', [
                    'merchant_transaction_id' => $merchantTransactionId,
                ]);

                return $empty;
            }
            $headers['Date'] = $date;
            $headers['X-Signature'] = $sig;
        }

        $httpCfg = HttpOutboundConfig::bankart('status_inquiry');

        try {
            $response = Http::withBasicAuth($username, $password)
                ->withHeaders($headers)
                ->connectTimeout($httpCfg['connect_timeout'])
                ->timeout($httpCfg['timeout'])
                ->get($url);
        } catch (Throwable $e) {
            Log::channel('payments')->warning('payment_status_inquiry_http_error', [
                'merchant_transaction_id' => $merchantTransactionId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return $empty;
        }

        $data = $response->json();
        if (! is_array($data)) {
            Log::channel('payments')->warning('payment_status_inquiry_http_error', [
                'merchant_transaction_id' => $merchantTransactionId,
                'http_status' => $response->status(),
                'reason' => 'invalid_json',
            ]);

            return ['outcome' => null, 'raw' => ['http_status' => $response->status()]];
        }

        if (($data['success'] ?? null) === false) {
            Log::channel('payments')->info('payment_status_inquiry_pending', [
                'merchant_transaction_id' => $merchantTransactionId,
                'http_status' => $response->status(),
                'error_code' => $data['errorCode'] ?? null,
                'error_message' => $data['errorMessage'] ?? null,
            ]);

            return ['outcome' => null, 'raw' => $data];
        }

        if (! $response->successful()) {
            Log::channel('payments')->warning('payment_status_inquiry_http_error', [
                'merchant_transaction_id' => $merchantTransactionId,
                'http_status' => $response->status(),
            ]);

            return ['outcome' => null, 'raw' => $data];
        }

        if (($data['success'] ?? null) !== true) {
            Log::channel('payments')->warning('payment_status_inquiry_unmapped_status', [
                'merchant_transaction_id' => $merchantTransactionId,
                'success_flag' => $data['success'] ?? null,
            ]);

            return ['outcome' => null, 'raw' => $data];
        }

        $txStatus = $data['transactionStatus'] ?? null;
        if (! is_string($txStatus)) {
            Log::channel('payments')->warning('payment_status_inquiry_unmapped_status', [
                'merchant_transaction_id' => $merchantTransactionId,
                'transaction_status' => $txStatus,
            ]);

            return ['outcome' => null, 'raw' => $data];
        }

        $normalized = strtoupper($txStatus);
        if ($normalized === 'SUCCESS') {
            Log::channel('payments')->info('payment_status_inquiry_success', [
                'merchant_transaction_id' => $merchantTransactionId,
                'transaction_status' => $txStatus,
            ]);

            return ['outcome' => 'success', 'raw' => $data];
        }

        if ($normalized === 'ERROR') {
            Log::channel('payments')->info('payment_status_inquiry_failed', [
                'merchant_transaction_id' => $merchantTransactionId,
                'transaction_status' => $txStatus,
            ]);

            return ['outcome' => 'failed', 'raw' => $data];
        }

        Log::channel('payments')->info('payment_status_inquiry_pending', [
            'merchant_transaction_id' => $merchantTransactionId,
            'transaction_status' => $txStatus,
        ]);

        return ['outcome' => null, 'raw' => $data];
    }
}
