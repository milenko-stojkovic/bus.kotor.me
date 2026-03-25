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

        $rawPayload = $this->buildFakeBankartCallbackPayload($temp, $scenario);

        $rawBody = json_encode($rawPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $contentType = 'application/json; charset=utf-8';
        $date = gmdate('D, d M Y H:i:s \G\M\T');
        $path = '/api/payment/callback';

        $server = [
            'CONTENT_TYPE' => $contentType,
            'HTTP_CONTENT_TYPE' => $contentType,
            'HTTP_DATE' => $date,
        ];

        $secret = config('services.bankart.shared_secret');
        if (! empty($secret)) {
            $bodyHash = hash('sha512', $rawBody);
            $message = implode("\n", ['POST', $bodyHash, $contentType, $date, $path]);
            $signature = base64_encode(hash_hmac('sha512', $message, $secret, true));
            $server['HTTP_X_SIGNATURE'] = $signature;
        }

        $fakeRequest = Request::create(url($path), 'POST', [], [], [], $server, $rawBody);

        $callbackController = app(PaymentCallbackController::class);
        $response = $callbackController->handle($fakeRequest, app(\App\Contracts\CallbackSignatureValidator::class));

        if ($response->getStatusCode() !== 202) {
            return redirect('/payment/error')->with('error', 'Callback rejected.');
        }

        return match ($scenario) {
            'success' => redirect('/payment/success'),
            'cancel' => redirect('/payment/cancel'),
            default => redirect('/payment/error'),
        };
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
