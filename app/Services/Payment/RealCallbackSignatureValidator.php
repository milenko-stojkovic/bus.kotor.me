<?php

namespace App\Services\Payment;

use App\Contracts\CallbackSignatureValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Bankart HMAC callback signature validator.
 *
 * Callback: POST /api/payment/callback
 * Headers: X-Signature, Date (ili X-Date), Content-Type = application/json; charset=utf-8
 * Message za potpis: POST + sha512_hex(raw_body) + Content-Type + Date + request_path
 * X-Signature = base64(HMAC-SHA512(message, shared_secret))
 * Shared secret: config('services.bankart.shared_secret')
 *
 * Callback MORA pasti ako: fali X-Signature, fali Date/X-Date, fali/nevaljan Content-Type,
 * rawBody nije korišćen za hash, path nije /api/payment/callback.
 */
class RealCallbackSignatureValidator implements CallbackSignatureValidator
{
    private const REQUIRED_PATH = '/api/payment/callback';

    private const REQUIRED_CONTENT_TYPE = 'application/json; charset=utf-8';

    public function validate(Request $request): bool
    {
        $signature = $request->header('X-Signature');
        if (! $signature) {
            return false;
        }

        $date = $request->header('Date') ?? $request->header('X-Date');
        if (! $date) {
            return false;
        }

        $contentType = $request->header('Content-Type');
        if (! $contentType) {
            return false;
        }
        if ($contentType !== self::REQUIRED_CONTENT_TYPE) {
            return false;
        }

        $rawBody = $request->getContent();
        if ($rawBody === null || $rawBody === '') {
            return false;
        }

        $path = parse_url($request->getRequestUri(), PHP_URL_PATH);
        if ($path !== self::REQUIRED_PATH) {
            return false;
        }

        $sharedSecret = config('services.bankart.shared_secret');
        if (! $sharedSecret) {
            return false;
        }

        $bodyHash = hash('sha512', $rawBody);

        $message = implode("\n", [
            'POST',
            $bodyHash,
            $contentType,
            $date,
            $path,
        ]);

        $expectedSignature = base64_encode(
            hash_hmac('sha512', $message, $sharedSecret, true)
        );

        if (! hash_equals($expectedSignature, $signature)) {
            if (config('app.debug')) {
                Log::channel('payments')->warning('Bankart callback signature mismatch', [
                    'expected' => $expectedSignature,
                    'received' => $signature,
                    'path' => $path,
                    'date' => $date,
                    'content_type' => $contentType,
                    'body_hash' => $bodyHash,
                ]);
            }

            return false;
        }

        return true;
    }
}
