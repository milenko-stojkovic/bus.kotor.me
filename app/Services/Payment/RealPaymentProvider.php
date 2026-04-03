<?php

namespace App\Services\Payment;

use App\Contracts\PaymentResult;
use App\Contracts\PaymentService;
use App\Contracts\PaymentSessionResult;
use App\Models\TempData;
use App\Support\HttpOutboundConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;
use RuntimeException;

class RealPaymentProvider implements PaymentService
{
    public function createSession(TempData $tempData): PaymentSessionResult
    {
        $cfg = config('services.bankart');
        $apiBase = rtrim((string) ($cfg['api_url'] ?? ''), '/');
        $apiKey = (string) ($cfg['api_key'] ?? '');
        $username = (string) ($cfg['username'] ?? '');
        $password = (string) ($cfg['password'] ?? '');
        $sharedSecret = (string) ($cfg['shared_secret'] ?? '');
        $signatureEnabled = (bool) ($cfg['signature_enabled'] ?? true);
        $sendCustomer = (bool) ($cfg['send_customer'] ?? true);

        if ($apiBase === '' || $apiKey === '' || $username === '' || $password === '' || ($signatureEnabled && $sharedSecret === '')) {
            Log::channel('payments')->warning('Bankart init missing configuration', [
                'merchant_transaction_id' => $tempData->merchant_transaction_id,
                'has_api_url' => $apiBase !== '',
                'has_api_key' => $apiKey !== '',
                'has_username' => $username !== '',
                'has_password' => $password !== '',
                'has_shared_secret' => $sharedSecret !== '',
                'signature_enabled' => $signatureEnabled,
            ]);
            return PaymentSessionResult::unavailable('Real payment gateway not configured.');
        }

        $amount = $this->resolveAmount($tempData);
        if ($amount === null) {
            Log::channel('payments')->warning('Bankart init missing amount source', [
                'merchant_transaction_id' => $tempData->merchant_transaction_id,
                'vehicle_type_id' => $tempData->vehicle_type_id,
            ]);
            return PaymentSessionResult::unavailable('Payment amount unavailable.');
        }

        $path = '/transaction/'.$apiKey.'/debit';
        $url = $apiBase.$path;
        $signaturePath = $this->resolveSignaturePath($apiBase, $path);
        $contentType = 'application/json; charset=utf-8';
        $date = gmdate('D, d M Y H:i:s').' GMT';

        $payload = [
            'merchantTransactionId' => $tempData->merchant_transaction_id,
            'amount' => $amount,
            'currency' => 'EUR',
            'successUrl' => route('payment.return', ['merchant_transaction_id' => $tempData->merchant_transaction_id, 'bank_result' => 'success']),
            'errorUrl' => route('payment.return', ['merchant_transaction_id' => $tempData->merchant_transaction_id, 'bank_result' => 'error']),
            'cancelUrl' => route('payment.return', ['merchant_transaction_id' => $tempData->merchant_transaction_id, 'bank_result' => 'cancel']),
            'callbackUrl' => route('api.payment.callback'),
        ];
        if ($sendCustomer) {
            $payload['customer'] = [
                'billingAddress1' => (string) (config('services.bankart.billing_address1') ?: ($tempData->country ? 'Address '.$tempData->country : 'Address')),
                'billingCity' => (string) (config('services.bankart.billing_city') ?: 'Kotor'),
                'billingCountry' => (string) ($tempData->country ?: 'ME'),
                'billingPostcode' => (string) (config('services.bankart.billing_postcode') ?: '85330'),
                'email' => $tempData->email,
            ];
        }

        $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($rawBody === false) {
            Log::channel('payments')->error('Bankart init payload encoding failed', [
                'merchant_transaction_id' => $tempData->merchant_transaction_id,
            ]);
            return PaymentSessionResult::unavailable('Payment payload encoding failed.');
        }

        $signature = null;
        if ($signatureEnabled) {
            $signature = $this->signRequest($rawBody, $contentType, $date, $signaturePath, $sharedSecret);
            if ($signature === null) {
                Log::channel('payments')->error('Bankart init signing failed', [
                    'merchant_transaction_id' => $tempData->merchant_transaction_id,
                ]);
                return PaymentSessionResult::unavailable('Payment signing failed.');
            }
        }

        Log::channel('payments')->info('Bankart init request start', [
            'merchant_transaction_id' => $tempData->merchant_transaction_id,
            'url' => $url,
            'path' => $path,
            'signature_path' => $signaturePath,
            'amount' => $amount,
            'currency' => 'EUR',
            'signature_enabled' => $signatureEnabled,
            'send_customer' => $sendCustomer,
        ]);

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => $contentType,
        ];
        if ($signatureEnabled) {
            $headers['Date'] = $date;
            $headers['X-Signature'] = (string) $signature;
        }

        $httpCfg = HttpOutboundConfig::bankart('create_session');

        try {
            $response = Http::withBasicAuth($username, $password)
                ->withHeaders($headers)
                ->withBody($rawBody, $contentType)
                ->connectTimeout($httpCfg['connect_timeout'])
                ->timeout($httpCfg['timeout'])
                ->post($url);
        } catch (Throwable $e) {
            Log::channel('payments')->error('Bankart init request failed', [
                'merchant_transaction_id' => $tempData->merchant_transaction_id,
                'message' => $e->getMessage(),
            ]);
            return PaymentSessionResult::unavailable('Payment gateway unavailable.');
        }

        $data = $response->json();
        if (! is_array($data)) {
            Log::channel('payments')->error('Bankart init invalid JSON response', [
                'merchant_transaction_id' => $tempData->merchant_transaction_id,
                'status' => $response->status(),
            ]);
            return PaymentSessionResult::unavailable('Invalid payment gateway response.');
        }

        $redirectUrl = $data['redirectUrl'] ?? null;
        if ($response->successful() && is_string($redirectUrl) && $redirectUrl !== '') {
            Log::channel('payments')->info('Bankart init request success', [
                'merchant_transaction_id' => $tempData->merchant_transaction_id,
                'status' => $response->status(),
                'uuid' => $data['uuid'] ?? null,
                'purchase_id' => $data['purchaseId'] ?? null,
                'payment_method' => $data['paymentMethod'] ?? null,
                'return_type' => $data['returnType'] ?? null,
            ]);
            return PaymentSessionResult::ok($redirectUrl);
        }

        $errorMessage = $data['message']
            ?? $data['error']
            ?? $response->reason()
            ?? 'Payment gateway rejected create session.';

        Log::channel('payments')->warning('Bankart init request failed response', [
            'merchant_transaction_id' => $tempData->merchant_transaction_id,
            'status' => $response->status(),
            'has_redirect_url' => is_string($redirectUrl) && $redirectUrl !== '',
            'error' => $errorMessage,
        ]);

        return PaymentSessionResult::unavailable((string) $errorMessage);
    }

    public function pay(TempData $tempData): PaymentResult
    {
        throw new RuntimeException('Real payment provider not implemented. Set PAYMENT_PROVIDER=fake or implement gateway call here.');
    }

    private function resolveAmount(TempData $tempData): ?string
    {
        $price = $tempData->vehicleType?->price;
        if ($price === null) {
            return null;
        }
        if (! is_numeric((string) $price)) {
            return null;
        }

        return number_format((float) $price, 2, '.', '');
    }

    private function signRequest(string $rawBody, string $contentType, string $date, string $path, string $sharedSecret): ?string
    {
        if ($sharedSecret === '') {
            return null;
        }

        $bodyHash = hash('sha512', $rawBody);
        $message = implode("\n", [
            'POST',
            $bodyHash,
            $contentType,
            $date,
            $path,
        ]);

        return base64_encode(hash_hmac('sha512', $message, $sharedSecret, true));
    }

    private function resolveSignaturePath(string $apiBase, string $path): string
    {
        $basePath = parse_url($apiBase, PHP_URL_PATH);
        $basePath = is_string($basePath) ? rtrim($basePath, '/') : '';
        return ($basePath !== '' ? $basePath : '').$path;
    }
}
