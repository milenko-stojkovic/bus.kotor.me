<?php

namespace App\Http\Controllers;

use App\Jobs\PaymentCallbackJob;
use App\Jobs\ProcessReservationAfterPaymentJob;
use App\Models\Reservation;
use App\Models\TempData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * SAMO test: kombinovana fake banka + fake fiskal u jednom submitu (ili GET complete sa opcionim fiscal_scenario).
 * Pravi callback ne poziva ovo — machine-to-machine ostaje POST /api/payment/callback.
 */
class FakeBankCompleteController extends Controller
{
    /** @var array<int, string> */
    private const FISCAL_SCENARIOS = [
        'success',
        'deposit_missing',
        'already_fiscalized',
        'validation_error',
        'provider_down',
        'tax_server_error',
        'temporary_service_down',
        'timeout',
        'malformed_response',
    ];

    private const BANK_SCENARIOS_RULE = 'success,cancel,expired,declined,insufficient_funds,3ds_failed,system_error';

    /**
     * GET /fake-bank/complete?tx=...&scenario=...&fiscal_scenario=... (fiscal_scenario podrazumijeva success).
     */
    public function completeGet(Request $request): RedirectResponse
    {
        $this->assertFakeBankDriver();

        $validated = $request->validate([
            'scenario' => ['nullable', 'string', 'in:'.self::BANK_SCENARIOS_RULE],
            'status' => ['nullable', 'string', 'in:success,error,cancel'],
            'tx' => ['required', 'string', 'max:64'],
            'fiscal_scenario' => ['nullable', 'string', 'in:'.implode(',', self::FISCAL_SCENARIOS)],
        ], [], [
            'tx' => 'merchant_transaction_id',
        ]);

        $merchantTransactionId = $validated['tx'];
        $bankScenario = $validated['scenario']
            ?? match ($validated['status'] ?? null) {
                'success' => 'success',
                'cancel' => 'cancel',
                'error' => 'system_error',
                default => 'success',
            };

        $fiscalScenario = $validated['fiscal_scenario'] ?? 'success';

        return $this->runFakeQaCompletion($merchantTransactionId, $bankScenario, $fiscalScenario);
    }

    /**
     * POST /payment/fake-bank/complete — jedna forma: bank_scenario + fiscal_scenario (fiscal važi samo kad je bank success).
     */
    public function completeForm(Request $request): RedirectResponse
    {
        $this->assertFakeBankDriver();

        $validated = $request->validate([
            'merchant_transaction_id' => ['required', 'string', 'max:64'],
            'bank_scenario' => ['required', 'string', 'in:'.self::BANK_SCENARIOS_RULE],
            'fiscal_scenario' => [
                'nullable',
                'string',
                'in:'.implode(',', self::FISCAL_SCENARIOS),
                Rule::requiredIf(fn () => $request->input('bank_scenario') === 'success'),
            ],
        ]);

        $fiscal = $validated['bank_scenario'] === 'success'
            ? (string) ($validated['fiscal_scenario'] ?? 'success')
            : null;

        return $this->runFakeQaCompletion(
            trim($validated['merchant_transaction_id']),
            $validated['bank_scenario'],
            $fiscal
        );
    }

    private function assertFakeBankDriver(): void
    {
        abort_unless(
            (config('services.bank.driver') ?? config('payment.provider', 'fake')) === 'fake',
            404
        );
    }

    private function runFakeQaCompletion(string $merchantTransactionId, string $bankScenario, ?string $fiscalScenario): RedirectResponse
    {
        $temp = TempData::where('merchant_transaction_id', $merchantTransactionId)->first();
        if (! $temp) {
            return redirect('/payment/error')->with('error', 'Transaction not found.');
        }

        $rawPayload = $this->buildFakeBankartCallbackPayload($temp, $bankScenario);

        $status = $bankScenario === 'success' ? 'success' : 'failed';
        $errorCode = $rawPayload['code'] ?? null;
        $errorReason = $rawPayload['message'] ?? null;

        $payload = [
            'merchant_transaction_id' => $merchantTransactionId,
            'status' => $status,
            'error_code' => $errorCode !== null ? (string) $errorCode : null,
            'error_reason' => is_string($errorReason) ? $errorReason : null,
        ];

        PaymentCallbackJob::dispatchSync($payload, $rawPayload);

        $this->runDeferredFakeFiscalPipeline($merchantTransactionId, $bankScenario === 'success', $fiscalScenario);

        return redirect()->route('payment.return', ['merchant_transaction_id' => $merchantTransactionId]);
    }

    /**
     * Kad su oba drivera fake, PaymentSuccessHandler ne šalje ProcessReservationAfterPaymentJob — šaljemo ovdje sa scenarijem iz forme.
     */
    private function runDeferredFakeFiscalPipeline(string $merchantTransactionId, bool $bankSuccess, ?string $fiscalScenario): void
    {
        if (! $bankSuccess) {
            return;
        }

        $bankFake = (config('services.bank.driver') ?? config('payment.provider', 'fake')) === 'fake';
        $fiscalFake = config('services.fiscalization.driver') === 'fake';
        if (! $bankFake || ! $fiscalFake) {
            return;
        }

        $scenario = is_string($fiscalScenario) && $fiscalScenario !== '' ? $fiscalScenario : 'success';

        $reservation = Reservation::query()
            ->where('merchant_transaction_id', $merchantTransactionId)
            ->first();

        if ($reservation && $reservation->status === 'paid') {
            ProcessReservationAfterPaymentJob::dispatchSync($reservation->id, $scenario);
        }
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
