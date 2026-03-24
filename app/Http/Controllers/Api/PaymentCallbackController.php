<?php

namespace App\Http\Controllers\Api;

use App\Contracts\CallbackSignatureValidator;
use App\Http\Controllers\Controller;
use App\Jobs\PaymentCallbackJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Bank callback endpoint – API only. Fully stateless.
 *
 * - No session, no cookies, no redirects.
 * - Returns simple HTTP: 202 Accepted or 400 Bad Request.
 * - User redirect is handled later via frontend polling GET /payment/result.
 */
class PaymentCallbackController extends Controller
{
    /**
     * Validate bank signature (first), then payload; dispatch job; return 202 or 400.
     */
    public function handle(Request $request, CallbackSignatureValidator $signatureValidator): JsonResponse
    {
        Log::channel('payments')->info('Payment callback received', [
            'ip' => $request->ip(),
            'content_type' => $request->header('Content-Type'),
            'has_signature' => $request->header('X-Signature') !== null,
        ]);

        if (! $signatureValidator->validate($request)) {
            Log::channel('payments')->warning('Payment callback signature invalid', [
                'ip' => $request->ip(),
                'merchant_transaction_id' => $request->input('merchant_transaction_id'),
            ]);

            return response()->json(['message' => 'Invalid callback signature.'], 400);
        }

        Log::channel('payments')->info('Payment callback signature valid', [
            'ip' => $request->ip(),
        ]);

        $rawBody = $request->getContent();
        $decoded = json_decode($rawBody, true);
        if (! is_array($decoded)) {
            Log::channel('payments')->warning('Payment callback malformed JSON', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Malformed callback JSON.'], 400);
        }

        $normalized = $this->normalizePayload($decoded);
        Log::channel('payments')->info('Payment callback normalized payload', [
            'merchant_transaction_id' => $normalized['merchant_transaction_id'] ?? null,
            'status' => $normalized['status'] ?? null,
            'result' => $decoded['result'] ?? null,
            'raw_status' => $decoded['status'] ?? null,
        ]);

        $validator = Validator::make($normalized, [
            'merchant_transaction_id' => ['required', 'string', 'max:64'],
            'status' => ['required', 'string', 'in:success,failed,timeout,CANCEL,ERROR'],
            'error_code' => ['nullable', 'string', 'max:64'],
            'error_reason' => ['nullable', 'string', 'max:500'],
        ]);
        if ($validator->fails()) {
            Log::channel('payments')->warning('Payment callback payload invalid', [
                'merchant_transaction_id' => $normalized['merchant_transaction_id'] ?? null,
                'status' => $normalized['status'] ?? null,
                'errors' => $validator->errors()->toArray(),
            ]);

            return response()->json(['message' => 'Invalid callback payload.'], 400);
        }
        $validated = $validator->validated();

        $rawPayload = $decoded;

        Log::channel('payments')->info('Payment callback accepted', [
            'merchant_transaction_id' => $validated['merchant_transaction_id'],
            'status' => $validated['status'],
            'ip' => $request->ip(),
        ]);
        if (config('app.debug')) {
            Log::channel('payments')->debug('Payment callback payload', ['payload' => $rawPayload]);
        }

        PaymentCallbackJob::dispatch($validated, $rawPayload);
        Log::channel('payments')->info('Payment callback job dispatched', [
            'merchant_transaction_id' => $validated['merchant_transaction_id'],
            'status' => $validated['status'],
        ]);

        return response()->json(['accepted' => true], 202);
    }

    /**
     * Real bank payload compatibility: support camelCase tx key and result-only callbacks.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        $txId = $payload['merchant_transaction_id']
            ?? $payload['merchantTransactionId']
            ?? null;

        $status = $payload['status'] ?? null;
        if ($status === null && isset($payload['result'])) {
            $result = strtoupper((string) $payload['result']);
            $status = match ($result) {
                'OK' => 'success',
                'CANCEL', 'ERROR', 'FAILED' => 'failed',
                default => null,
            };
        }

        if (is_string($status) && strtoupper($status) === 'OK') {
            $status = 'success';
        }

        return [
            ...$payload,
            'merchant_transaction_id' => $txId,
            'status' => $status,
            'error_code' => $payload['error_code'] ?? $payload['errorCode'] ?? null,
            'error_reason' => $payload['error_reason'] ?? $payload['errorReason'] ?? null,
        ];
    }
}
