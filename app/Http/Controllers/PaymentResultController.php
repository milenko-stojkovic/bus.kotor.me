<?php

namespace App\Http\Controllers;

use App\Services\Payment\PaymentResultResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API: status plaćanja iz baze (jedini izvor istine). Frontend / polling poziva ovaj endpoint.
 * Vraća JSON: { status, user_type, message?, redirect_guest, redirect_auth }.
 */
class PaymentResultController extends Controller
{
    /**
     * GET /payment/result?merchant_transaction_id=...
     * Vraća status (success|failed|pending), user_type (guest|auth), message za failed.
     */
    public function __invoke(Request $request, PaymentResultResolver $resolver): JsonResponse
    {
        $txId = $request->query('merchant_transaction_id');
        if (! $txId || ! is_string($txId)) {
            return response()->json([
                'status' => 'failed',
                'user_type' => 'guest',
                'message' => PaymentResultResolver::MESSAGE_FAILED_FALLBACK,
                'redirect_guest' => route('reservations.create'),
                'redirect_auth' => route('panel.reservations'),
            ], 400);
        }

        $result = $resolver->resolve($txId);
        if ($result) {
            return response()->json($result);
        }

        return response()->json([
            'status' => 'failed',
            'user_type' => 'guest',
            'message' => PaymentResultResolver::MESSAGE_FAILED_FALLBACK,
            'redirect_guest' => route('reservations.create'),
            'redirect_auth' => route('panel.reservations'),
        ], 404);
    }
}
