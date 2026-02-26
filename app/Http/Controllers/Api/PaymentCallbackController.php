<?php

namespace App\Http\Controllers\Api;

use App\Contracts\CallbackSignatureValidator;
use App\Http\Controllers\Controller;
use App\Jobs\PaymentCallbackJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
        if (! $signatureValidator->validate($request)) {
            Log::channel('payments')->warning('Payment callback signature invalid', [
                'ip' => $request->ip(),
                'merchant_transaction_id' => $request->input('merchant_transaction_id'),
            ]);

            return response()->json(['message' => 'Invalid callback signature.'], 400);
        }

        $validated = $request->validate([
            'merchant_transaction_id' => ['required', 'string', 'max:64'],
            'status' => ['required', 'string', 'in:success,failed,timeout,CANCEL,ERROR'],
            'error_code' => ['nullable', 'string', 'max:64'],
            'error_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $rawPayload = $request->all();

        Log::channel('payments')->info('Payment callback accepted', [
            'merchant_transaction_id' => $validated['merchant_transaction_id'],
            'status' => $validated['status'],
            'ip' => $request->ip(),
        ]);
        if (config('app.debug')) {
            Log::channel('payments')->debug('Payment callback payload', ['payload' => $rawPayload]);
        }

        PaymentCallbackJob::dispatch($validated, $rawPayload);

        return response()->json(['accepted' => true], 202);
    }
}
