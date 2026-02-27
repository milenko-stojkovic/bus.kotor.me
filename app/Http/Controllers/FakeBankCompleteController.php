<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\PaymentCallbackController;
use App\Jobs\PaymentCallbackJob;
use App\Models\TempData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * SAMO za test: simulacija banke (fake bank). Dva endpointa:
 *
 * 1) GET /fake-bank/complete?status=success|error|cancel&tx={merchant_transaction_id}
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
     * GET /fake-bank/complete?status=success|error|cancel&tx={merchant_transaction_id}
     * Generiše fake Bankart callback, poziva PaymentCallbackController@handle, redirect.
     */
    public function completeGet(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:success,error,cancel'],
            'tx' => ['required', 'string', 'max:64'],
        ], [], [
            'tx' => 'merchant_transaction_id',
        ]);

        $merchantTransactionId = $validated['tx'];
        $outcome = $validated['status'];

        $temp = TempData::where('merchant_transaction_id', $merchantTransactionId)->first();
        if (! $temp) {
            return redirect('/payment/error')->with('error', 'Transaction not found.');
        }

        $callbackStatus = match ($outcome) {
            'success' => 'success',
            'error' => 'failed',
            'cancel' => 'CANCEL',
        };

        $payload = [
            'merchant_transaction_id' => $merchantTransactionId,
            'status' => $callbackStatus,
        ];
        if ($outcome !== 'success') {
            $payload['error_code'] = $outcome === 'cancel' ? 'USER_CANCEL' : 'ERROR';
            $payload['error_reason'] = $outcome === 'cancel' ? 'User cancelled.' : 'Simulated error.';
        }

        $rawBody = json_encode($payload);
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

        return match ($outcome) {
            'success' => redirect('/payment/success'),
            'error' => redirect('/payment/error'),
            'cancel' => redirect('/payment/cancel'),
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

        $rawPayload = $request->only(['merchant_transaction_id', 'status']);
        PaymentCallbackJob::dispatch($validated, $rawPayload);

        return redirect()->route('payment.return', [
            'merchant_transaction_id' => $validated['merchant_transaction_id'],
        ]);
    }
}
