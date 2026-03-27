<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\PaymentCallbackController;
use App\Jobs\PaymentCallbackJob;
use App\Models\TempData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * SAMO za test: simulacija banke (fake bank). Dva endpointa:
 *
 * 1) GET /fake-bank/complete?scenario=success|cancel|expired|declined|insufficient_funds|3ds_failed|system_error&tx={merchant_transaction_id}
 *    (backward compat: status=success|error|cancel)
 *    - Pronalazi transakciju po tx, gradi fake Bankart callback payload, interno poziva
 *    - PaymentCallbackController@handle, zatim redirect na /payment/success|error|cancel.
 *
 * 2) POST /payment/fake-bank/complete (merchant_transaction_id, status=success|failed)
 *    - Dispatch PaymentCallbackJob, redirect na payment.return.
 *
 * Bank callback = POST /api/payment/callback = machine-to-machine only.
 */
class FakeBankCompleteController extends Controller
{
    /**
     * GET /fake-bank/complete?scenario=...&tx=...
     * Generiše fake Bankart callback, poziva PaymentCallbackController@handle, redirect.
     */
    public function completeGet(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'scenario' => ['nullable', 'string', 'in:success,cancel,expired,declined,insufficient_funds,3ds_failed,system_error'],
            'status' => ['nullable', 'string', 'in:success,error,cancel'],
            'tx' => ['required', 'string', 'max:64'],
        ], [], [
            'tx' => 'merchant_transaction_id',
        ]);

        $merchantTransactionId = $validated['tx'];
        $scenario = $validated['scenario']
            ?? match ($validated['status'] ?? null) {
                'success' => 'success',
                'cancel' => 'cancel',
                'error' => 'system_error',
                default => 'success',
            };

        $temp = TempData::where('merchant_transaction_id', $merchantTransactionId)->first();
        if (! $temp) {
            return redirect('/payment/error')->with('error', 'Transaction not found.');
        }

        // Test-only: bypass signature/controller and dispatch job directly with a real-like raw payload.
        $rawPayload = $this->buildFakeBankartCallbackPayload($temp, $scenario);

        $status = $scenario === 'success' ? 'success' : 'failed';
        $errorCode = $rawPayload['code'] ?? null;
        $errorReason = $rawPayload['message'] ?? null;

        PaymentCallbackJob::dispatch([
            'merchant_transaction_id' => $merchantTransactionId,
            'status' => $status,
            'error_code' => $errorCode !== null ? (string) $errorCode : null,
            'error_reason' => is_string($errorReason) ? $errorReason : null,
        ], $rawPayload);

        return redirect()->route('payment.return', ['merchant_transaction_id' => $merchantTransactionId]);
    }

    /**
     * POST /payment/fake-bank/complete – form submit (Success/Fail dugme).
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'merchant_transaction_id' => ['required', 'string', 'max:64'],
            'status' => ['required', 'string', 'in:success,failed'],
        ]);

        // Backward-compatible: this path dispatches job directly. Keep it simple but provide raw payload in real shape.
        $scenario = $validated['status'] === 'success' ? 'success' : 'system_error';
        $temp = TempData::where('merchant_transaction_id', $validated['merchant_transaction_id'])->first();
        $rawPayload = $temp ? $this->buildFakeBankartCallbackPayload($temp, $scenario) : [
            'result' => $scenario === 'success' ? 'OK' : 'ERROR',
            'merchantTransactionId' => $validated['merchant_transaction_id'],
        ];

        PaymentCallbackJob::dispatch([
            'merchant_transaction_id' => $validated['merchant_transaction_id'],
            'status' => $scenario === 'success' ? 'success' : 'failed',
            // Keep error_code/reason empty here; job will fall back to rawPayload code/message if needed.
        ], $rawPayload);

        return redirect()->route('payment.return', [
            'merchant_transaction_id' => $validated['merchant_transaction_id'],
        ]);
    }

    private function buildFakeBankartCallbackPayload(TempData $temp, string $scenario): array
    {
        $uuid = Str::uuid()->toString();
        $purchaseId = 'FAKE-PURCHASE-'.Str::uuid()->toString();
        $amount = 0;
        $currency = 'EUR';

        $base = [
            'uuid' => $uuid,
            'merchantTransactionId' => $temp->merchant_transaction_id,
            'purchaseId' => $purchaseId,
            'transactionType' => 'DEBIT',
            'paymentMethod' => 'Creditcard',
            'amount' => $amount,
            'currency' => $currency,
        ];

        if ($scenario === 'success') {
            return [
                ...$base,
                'result' => 'OK',
            ];
        }

        $error = match ($scenario) {
            'cancel' => [
                'code' => 2002,
                'message' => 'User cancelled',
            ],
            'expired' => [
                'code' => 2005,
                'message' => 'Transaction expired',
            ],
            'declined' => [
                'code' => 2003,
                'message' => 'Authorization declined',
            ],
            'insufficient_funds' => [
                'code' => 2006,
                'message' => 'Insufficient funds',
                'adapterCode' => 51,
                'adapterMessage' => 'Insufficient funds',
            ],
            '3ds_failed' => [
                'code' => 2021,
                'message' => '3DS authentication failed',
                'adapterCode' => 'R',
                'adapterMessage' => '3DS failed',
            ],
            default => [
                'code' => 9999,
                'message' => 'System failure',
            ],
        };

        return [
            ...$base,
            'result' => 'ERROR',
            ...$error,
        ];
    }
}
