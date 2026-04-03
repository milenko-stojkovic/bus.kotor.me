<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\SystemConfig;
use App\Services\Payment\ErrorClassifier;
use App\Support\HttpOutboundConfig;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fiskalizacija rezervacije. Vraća ['fiscal_jir' => ..., ...] na uspeh ili ['error' => message] na neuspeh.
 * Driver iz config('services.fiscalization.driver'): fake = POST na local fake efiscal endpoints (deposit + receipt), real = pravi API.
 * HTTP connect/response timeout: {@see config('http-outbound.fiscal')}.
 */
class FiscalizationService
{
    private const FISCAL_DEPOSIT_ENDPOINT = '/api/efiscal/deposit';

    private const FISCAL_RECEIPT_ENDPOINT = '/api/efiscal/fiscalReceipt';

    private const DOCUMENT_CONFIG_KEY = 'document_number';

    /**
     * Poziv fiskalnog API-ja. Na uspeh vraća niz sa fiscal_jir (i ostalim poljima); na neuspeh ['error' => string].
     */
    public function tryFiscalize(Reservation $reservation, ?string $forcedFakeScenario = null): array
    {
        $driver = config('services.fiscalization.driver', 'fake');

        if ($driver === 'fake') {
            return $this->callFakeFiscalization($reservation, $forcedFakeScenario);
        }

        return $this->callRealFiscalization($reservation);
    }

    /**
     * POST na naš fake endpoint (isti payload kao za real servis).
     */
    private function callFakeFiscalization(Reservation $reservation, ?string $forcedFakeScenario = null): array
    {
        $documentNumber = $this->nextDocumentNumber();

        // Fake driver MUST mirror real API contract (success: ResponseCode=JIR, UIDRequest=IKOF, Url.Value=QR, Operator; errors: Error.ErrorCode / ErrorMessage).
        // Align fake flow with real provider contract: deposit -> receipt (same shape as real).
        $payload = [
            ...$this->buildFiscalPayload($reservation),
            // Real provider identity; include in fake payload so the fake service can echo it back.
            'ENUIdentifier' => (string) config('services.fiscal.enu_identifier'),
            // Make fake closer to real: pass document number so fiscal_qr can include ord=...
            'DocumentNumber' => $documentNumber,
            // Used by fake verify URL to embed year (crtd=...).
            'CreatedAt' => ($reservation->created_at ?? now())->toIso8601String(),
            // Used by fake verify URL (prc=...); not critical for internal number parsing, but keeps URL realistic.
            'Price' => (float) ($reservation->vehicleType?->price ?? 0),
        ];

        $scenario = '';
        if ($forcedFakeScenario !== null && trim($forcedFakeScenario) !== '') {
            $scenario = trim($forcedFakeScenario);
        }
        if ($scenario === '') {
            $scenario = (string) (config('services.fiscalization.fake_scenario') ?? '');
            $scenario = trim($scenario);
        }
        if ($scenario === '') {
            try {
                $sessionScenario = request()->session()->get('fiscal_fake_scenario');
                if (is_string($sessionScenario)) {
                    $scenario = trim($sessionScenario);
                }
            } catch (Throwable) {
                // no session/request context (e.g. queue worker)
            }
        }

        $depositUrl = url(route('api.efiscal.deposit'));
        $receiptUrl = url(route('api.efiscal.fiscal-receipt'));
        if ($scenario !== '') {
            $depositUrl .= '?scenario='.urlencode($scenario);
            $receiptUrl .= '?scenario='.urlencode($scenario);
        }

        $depositResp = $this->fiscalHttpClient('deposit')->post($depositUrl, $payload);

        $depositData = $depositResp->json();
        if (! $depositResp->successful() || ! is_array($depositData)) {
            $classified = app(ErrorClassifier::class)->classify('fiscal', 500, 'Invalid fiscal deposit response.', is_array($depositData) ? $depositData : null);

            return [
                'error' => 'Invalid fiscal deposit response.',
                ...$classified,
            ];
        }

        $depositSuccess = $depositData['IsSucccess'] ?? $depositData['IsSuccess'] ?? null;
        $depositSuccess = $depositSuccess === true || $depositSuccess === 1 || $depositSuccess === '1' || $depositSuccess === 'true';
        if (! $depositSuccess) {
            $errorCode = $depositData['Error']['ErrorCode'] ?? $depositData['ErrorCode'] ?? null;
            $errorMessage = $depositData['Error']['ErrorMessage']
                ?? $depositData['ErrorMessage']
                ?? $depositData['message']
                ?? $depositResp->reason()
                ?? 'Fiscal deposit error';
            $classified = app(ErrorClassifier::class)->classify('fiscal', $errorCode, is_string($errorMessage) ? $errorMessage : null, $depositData);

            return [
                'error' => (string) $errorMessage,
                ...$classified,
            ];
        }

        $receiptResp = $this->fiscalHttpClient('receipt')->post($receiptUrl, $payload);

        $data = $receiptResp->json();
        if (! $receiptResp->successful() || ! is_array($data)) {
            $classified = app(ErrorClassifier::class)->classify('fiscal', 500, 'Invalid fiscal service response.', is_array($data) ? $data : null);

            return [
                'error' => 'Invalid fiscal service response.',
                ...$classified,
            ];
        }

        $isSuccess = $data['IsSucccess'] ?? $data['IsSuccess'] ?? null;
        $isSuccess = $isSuccess === true || $isSuccess === 1 || $isSuccess === '1' || $isSuccess === 'true';
        if ($isSuccess) {
            $jir = $data['ResponseCode'] ?? null;
            $ikof = $data['UIDRequest'] ?? null;
            $qr = $data['Url']['Value'] ?? null;
            if (! is_string($jir) || $jir === '' || ! is_string($ikof) || $ikof === '') {
                $classified = app(ErrorClassifier::class)->classify('fiscal', 500, 'Fiscal service success without required fields.', $data);

                return [
                    'error' => 'Fiscal service success without required fields.',
                    ...$classified,
                ];
            }

            return [
                'fiscal_jir' => $jir,
                'fiscal_ikof' => $ikof,
                'fiscal_qr' => is_string($qr) ? $qr : null,
                'fiscal_operator' => $data['Operator'] ?? null,
                'fiscal_date' => now(),
            ];
        }

        $errorCode = $data['Error']['ErrorCode'] ?? $data['ErrorCode'] ?? null;
        $errorMessage = $data['Error']['ErrorMessage']
            ?? $data['ErrorMessage']
            ?? $data['message']
            ?? $receiptResp->reason()
            ?? 'Fiscal service error';

        $classified = app(ErrorClassifier::class)->classify('fiscal', $errorCode, is_string($errorMessage) ? $errorMessage : null, $data);

        // Same semantics as real: if 58, retry deposit + receipt once.
        if ((string) $errorCode === '58') {
            $retryDeposit = $this->fiscalHttpClient('deposit')->post($depositUrl, $payload);
            $retryDepositData = $retryDeposit->json();
            if ($retryDeposit->successful() && is_array($retryDepositData)) {
                $retryReceipt = $this->fiscalHttpClient('receipt')->post($receiptUrl, $payload);
                $retryData = $retryReceipt->json();
                if ($retryReceipt->successful() && is_array($retryData)) {
                    $retryIsSuccess = $retryData['IsSucccess'] ?? $retryData['IsSuccess'] ?? null;
                    $retryIsSuccess = $retryIsSuccess === true || $retryIsSuccess === 1 || $retryIsSuccess === '1' || $retryIsSuccess === 'true';
                    if ($retryIsSuccess) {
                        $jir = $retryData['ResponseCode'] ?? null;
                        $ikof = $retryData['UIDRequest'] ?? null;
                        $qr = $retryData['Url']['Value'] ?? null;
                        if (is_string($jir) && $jir !== '' && is_string($ikof) && $ikof !== '') {
                            return [
                                'fiscal_jir' => $jir,
                                'fiscal_ikof' => $ikof,
                                'fiscal_qr' => is_string($qr) ? $qr : null,
                                'fiscal_operator' => $retryData['Operator'] ?? null,
                                'fiscal_date' => now(),
                            ];
                        }
                    }
                }
            }
        }

        return [
            'error' => (string) $errorMessage,
            ...$classified,
        ];
    }

    /**
     * Real fiskalni servis (trenutno stub; kasnije HTTP na config URL).
     */
    private function callRealFiscalization(Reservation $reservation): array
    {
        $apiUrl = $this->normalizeFiscalApiBaseUrl((string) config('services.fiscal.api_url'));
        $token = (string) config('services.fiscal.api_token');
        $enuIdentifier = (string) config('services.fiscal.enu_identifier');
        $userCode = (string) config('services.fiscal.user_code');
        $userName = (string) config('services.fiscal.user_name');

        if ($apiUrl === '' || $token === '') {
            Log::channel('payments')->warning('Real fiscalization missing configuration', [
                'reservation_id' => $reservation->id,
                'merchant_transaction_id' => $reservation->merchant_transaction_id,
                'has_api_url' => $apiUrl !== '',
                'has_api_token' => $token !== '',
            ]);

            $classified = app(ErrorClassifier::class)->classify('fiscal', 500, 'Fiscal service not configured.', null);

            return [
                'error' => 'Fiscal service not configured.',
                ...$classified,
            ];
        }

        if ($enuIdentifier === '' || $userCode === '' || $userName === '') {
            Log::channel('payments')->warning('Real fiscalization missing deposit identity configuration', [
                'reservation_id' => $reservation->id,
                'merchant_transaction_id' => $reservation->merchant_transaction_id,
                'has_enu_identifier' => $enuIdentifier !== '',
                'has_user_code' => $userCode !== '',
                'has_user_name' => $userName !== '',
            ]);

            $classified = app(ErrorClassifier::class)->classify('fiscal', 11, 'Fiscal deposit identity not configured.', null);

            return [
                'error' => 'Fiscal deposit identity not configured.',
                ...$classified,
            ];
        }

        $depositEndpoint = $apiUrl.self::FISCAL_DEPOSIT_ENDPOINT;
        $receiptEndpoint = $apiUrl.self::FISCAL_RECEIPT_ENDPOINT;
        $documentNumber = $this->nextDocumentNumber();
        Log::channel('payments')->info('Fiscal document number reserved', [
            'reservation_id' => $reservation->id,
            'merchant_transaction_id' => $reservation->merchant_transaction_id,
            'document_number' => $documentNumber,
        ]);

        $receiptPayload = [
            ...$this->buildFiscalPayload($reservation),
            'DocumentType' => 'INVOICE',
            'DocumentNumber' => $documentNumber,
        ];

        // Deposit must exist for CARD/CASH receipts. Safe approach: send Amount=0 before every receipt attempt.
        $depositResult = $this->callRealDeposit(
            reservation: $reservation,
            endpoint: $depositEndpoint,
            token: $token,
            enuIdentifier: $enuIdentifier,
            userCode: $userCode,
            userName: $userName
        );
        if ($depositResult !== null) {
            return $depositResult; // error array
        }

        Log::channel('payments')->info('Real fiscalization request start', [
            'reservation_id' => $reservation->id,
            'merchant_transaction_id' => $reservation->merchant_transaction_id,
            'endpoint' => $receiptEndpoint,
        ]);

        try {
            $response = $this->fiscalHttpClient('receipt')
                ->withToken($token)
                ->post($receiptEndpoint, $receiptPayload);
        } catch (Throwable $e) {
            Log::channel('payments')->error('Real fiscalization request failed', [
                'reservation_id' => $reservation->id,
                'merchant_transaction_id' => $reservation->merchant_transaction_id,
                'message' => $e->getMessage(),
            ]);

            $classified = app(ErrorClassifier::class)->classify('fiscal', 500, 'Fiscal service unavailable.', null);

            return [
                'error' => 'Fiscal service unavailable.',
                ...$classified,
            ];
        }

        $data = $response->json();
        if (! is_array($data)) {
            Log::channel('payments')->error('Real fiscalization non-JSON response', [
                'reservation_id' => $reservation->id,
                'merchant_transaction_id' => $reservation->merchant_transaction_id,
                'status' => $response->status(),
            ]);

            $classified = app(ErrorClassifier::class)->classify('fiscal', 500, 'Invalid fiscal service response.', null);

            return [
                'error' => 'Invalid fiscal service response.',
                ...$classified,
            ];
        }

        // Expected success shape (mirrors FakeFiscalApiController):
        // { IsSucccess: true, ResponseCode: JIR, UIDRequest: IKOF, Url: { Value: qr } }
        $isSuccess = $data['IsSucccess'] ?? $data['IsSuccess'] ?? null;
        $isSuccess = $isSuccess === true || $isSuccess === 1 || $isSuccess === '1' || $isSuccess === 'true';
        if ($isSuccess) {
            $jir = $data['ResponseCode'] ?? null;
            $ikof = $data['UIDRequest'] ?? null;
            $qr = $data['Url']['Value'] ?? null;

            if (! is_string($jir) || $jir === '' || ! is_string($ikof) || $ikof === '') {
                Log::channel('payments')->error('Real fiscalization success missing fields', [
                    'reservation_id' => $reservation->id,
                    'merchant_transaction_id' => $reservation->merchant_transaction_id,
                    'has_jir' => is_string($jir) && $jir !== '',
                    'has_ikof' => is_string($ikof) && $ikof !== '',
                ]);

                $classified = app(ErrorClassifier::class)->classify('fiscal', 500, 'Fiscal service success without required fields.', null);

                return [
                    'error' => 'Fiscal service success without required fields.',
                    ...$classified,
                ];
            }

            Log::channel('payments')->info('Real fiscalization request success', [
                'reservation_id' => $reservation->id,
                'merchant_transaction_id' => $reservation->merchant_transaction_id,
                'status' => $response->status(),
            ]);

            return [
                'fiscal_jir' => $jir,
                'fiscal_ikof' => $ikof,
                'fiscal_qr' => is_string($qr) ? $qr : null,
                'fiscal_operator' => $data['Operator'] ?? null,
                'fiscal_date' => now(),
            ];
        }

        // Error shape (mirrors FakeFiscalApiController):
        // { IsSucccess: false, Error: { ErrorCode, ErrorMessage } }
        $errorCode = $data['Error']['ErrorCode'] ?? $data['ErrorCode'] ?? null;
        $errorMessage = $data['Error']['ErrorMessage']
            ?? $data['ErrorMessage']
            ?? $data['message']
            ?? $response->reason()
            ?? 'Fiscal service error';

        Log::channel('payments')->warning('Real fiscalization failed response', [
            'reservation_id' => $reservation->id,
            'merchant_transaction_id' => $reservation->merchant_transaction_id,
            'status' => $response->status(),
            'error_code' => $errorCode,
            'error' => $errorMessage,
        ]);

        $classified = app(ErrorClassifier::class)->classify('fiscal', $errorCode, is_string($errorMessage) ? $errorMessage : null, null);

        // Provider: ErrorCode 58 = deposit missing. Try deposit once more then retry receipt once.
        if ((string) $errorCode === '58') {
            Log::channel('payments')->warning('Real fiscalization indicates missing deposit (58); retrying deposit + receipt once', [
                'reservation_id' => $reservation->id,
                'merchant_transaction_id' => $reservation->merchant_transaction_id,
            ]);

            $depositResult = $this->callRealDeposit(
                reservation: $reservation,
                endpoint: $depositEndpoint,
                token: $token,
                enuIdentifier: $enuIdentifier,
                userCode: $userCode,
                userName: $userName
            );
            if ($depositResult !== null) {
                return $depositResult;
            }

            try {
                $retryResp = $this->fiscalHttpClient('receipt')
                    ->withToken($token)
                    ->post($receiptEndpoint, $receiptPayload);
            } catch (Throwable $e) {
                Log::channel('payments')->error('Real fiscalization retry request failed', [
                    'reservation_id' => $reservation->id,
                    'merchant_transaction_id' => $reservation->merchant_transaction_id,
                    'message' => $e->getMessage(),
                ]);
                $retryClassified = app(ErrorClassifier::class)->classify('fiscal', 500, 'Fiscal service unavailable.', null);

                return [
                    'error' => 'Fiscal service unavailable.',
                    ...$retryClassified,
                ];
            }

            $retryData = $retryResp->json();
            if (is_array($retryData)) {
                $retryIsSuccess = $retryData['IsSucccess'] ?? $retryData['IsSuccess'] ?? null;
                $retryIsSuccess = $retryIsSuccess === true || $retryIsSuccess === 1 || $retryIsSuccess === '1' || $retryIsSuccess === 'true';
                if ($retryIsSuccess) {
                    $jir = $retryData['ResponseCode'] ?? null;
                    $ikof = $retryData['UIDRequest'] ?? null;
                    $qr = $retryData['Url']['Value'] ?? null;
                    if (is_string($jir) && $jir !== '' && is_string($ikof) && $ikof !== '') {
                        Log::channel('payments')->info('Real fiscalization retry success', [
                            'reservation_id' => $reservation->id,
                            'merchant_transaction_id' => $reservation->merchant_transaction_id,
                            'status' => $retryResp->status(),
                        ]);

                        return [
                            'fiscal_jir' => $jir,
                            'fiscal_ikof' => $ikof,
                            'fiscal_qr' => is_string($qr) ? $qr : null,
                            'fiscal_operator' => $retryData['Operator'] ?? null,
                            'fiscal_date' => now(),
                        ];
                    }
                }
            }
        }

        return [
            'error' => (string) $errorMessage,
            ...$classified,
        ];
    }

    /**
     * Reserve next fiscal DocumentNumber (monotonic, concurrency-safe, gaps allowed).
     * Returns the reserved number for current receipt; persists incremented value.
     */
    private function nextDocumentNumber(): int
    {
        return (int) DB::transaction(function (): int {
            $row = SystemConfig::query()
                ->where('name', self::DOCUMENT_CONFIG_KEY)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                // Create baseline then lock it.
                SystemConfig::query()->updateOrInsert(
                    ['name' => self::DOCUMENT_CONFIG_KEY],
                    ['value' => 1, 'updated_at' => now()]
                );
                $row = SystemConfig::query()
                    ->where('name', self::DOCUMENT_CONFIG_KEY)
                    ->lockForUpdate()
                    ->first();
            }

            $current = (int) ($row?->value ?? 1);
            if ($current < 1) {
                $current = 1;
            }

            // Persist next value; no rollback on later fiscal failures (gaps allowed).
            SystemConfig::query()
                ->where('name', self::DOCUMENT_CONFIG_KEY)
                ->update(['value' => $current + 1, 'updated_at' => now()]);

            return $current;
        });
    }

    /**
     * @return array{error: string, resolution_reason?: string, category?: string, notify_admin?: bool, user_message_key?: string, retryable?: bool}|null null = success, array = failure
     */
    private function callRealDeposit(
        Reservation $reservation,
        string $endpoint,
        string $token,
        string $enuIdentifier,
        string $userCode,
        string $userName
    ): ?array {
        $uid = (string) \Illuminate\Support\Str::uuid();
        $payload = [
            'UID' => $uid,
            'ENUIdentifier' => $enuIdentifier,
            'Type' => 'json',
            'DepositType' => 'INITIAL',
            'Amount' => 0,
            'DateSend' => now()->toIso8601String(),
            'User' => [
                'UserCode' => $userCode,
                'UserName' => $userName,
            ],
        ];

        Log::channel('payments')->info('Real fiscal deposit request start', [
            'reservation_id' => $reservation->id,
            'merchant_transaction_id' => $reservation->merchant_transaction_id,
            'endpoint' => $endpoint,
            'uid' => $uid,
            'amount' => 0,
        ]);

        try {
            $response = $this->fiscalHttpClient('deposit')
                ->withToken($token)
                ->post($endpoint, $payload);
        } catch (Throwable $e) {
            Log::channel('payments')->error('Real fiscal deposit request failed', [
                'reservation_id' => $reservation->id,
                'merchant_transaction_id' => $reservation->merchant_transaction_id,
                'message' => $e->getMessage(),
            ]);
            $classified = app(ErrorClassifier::class)->classify('fiscal', 500, 'Fiscal deposit unavailable.', null);

            return [
                'error' => 'Fiscal deposit unavailable.',
                ...$classified,
            ];
        }

        $data = $response->json();
        if (! is_array($data)) {
            Log::channel('payments')->error('Real fiscal deposit non-JSON response', [
                'reservation_id' => $reservation->id,
                'merchant_transaction_id' => $reservation->merchant_transaction_id,
                'status' => $response->status(),
            ]);
            $classified = app(ErrorClassifier::class)->classify('fiscal', 500, 'Invalid fiscal deposit response.', null);

            return [
                'error' => 'Invalid fiscal deposit response.',
                ...$classified,
            ];
        }

        $isSuccess = $data['IsSucccess'] ?? $data['IsSuccess'] ?? null;
        $isSuccess = $isSuccess === true || $isSuccess === 1 || $isSuccess === '1' || $isSuccess === 'true';
        if ($isSuccess) {
            Log::channel('payments')->info('Real fiscal deposit request success', [
                'reservation_id' => $reservation->id,
                'merchant_transaction_id' => $reservation->merchant_transaction_id,
                'status' => $response->status(),
            ]);

            return null;
        }

        $errorCode = $data['Error']['ErrorCode'] ?? $data['ErrorCode'] ?? null;
        $errorMessage = $data['Error']['ErrorMessage']
            ?? $data['ErrorMessage']
            ?? $data['message']
            ?? $response->reason()
            ?? 'Fiscal deposit error';

        Log::channel('payments')->warning('Real fiscal deposit failed response', [
            'reservation_id' => $reservation->id,
            'merchant_transaction_id' => $reservation->merchant_transaction_id,
            'status' => $response->status(),
            'error_code' => $errorCode,
            'error' => $errorMessage,
        ]);

        $classified = app(ErrorClassifier::class)->classify('fiscal', $errorCode, is_string($errorMessage) ? $errorMessage : null, null);

        return [
            'error' => (string) $errorMessage,
            ...$classified,
        ];
    }

    private function normalizeFiscalApiBaseUrl(string $baseUrl): string
    {
        $baseUrl = rtrim(trim($baseUrl), '/');
        if ($baseUrl === '') {
            return '';
        }

        // Defensive: if someone configured base URL with /api already, strip it to avoid /api/api.
        if (str_ends_with($baseUrl, '/api')) {
            $baseUrl = substr($baseUrl, 0, -4);
            $baseUrl = rtrim($baseUrl, '/');
        }

        return $baseUrl;
    }

    /**
     * Osnovni JSON deo koji ide i na fake i na real receipt (proširenje u callFakeFiscalization / callRealFiscalization).
     * Fake driver MUST mirror real API contract — dodavanje polja ovde zahteva isto polje u real payload-u gde je primenljivo.
     */
    private function buildFiscalPayload(Reservation $reservation): array
    {
        return [
            'reservation_id' => $reservation->id,
            'merchant_transaction_id' => $reservation->merchant_transaction_id,
            'reservation_date' => $reservation->reservation_date?->format('Y-m-d'),
            'user_name' => $reservation->user_name,
            'email' => $reservation->email,
            'license_plate' => $reservation->license_plate,
            'country' => $reservation->country,
        ];
    }

    /**
     * @param  'deposit'|'receipt'  $endpoint
     */
    private function fiscalHttpClient(string $endpoint): PendingRequest
    {
        $t = HttpOutboundConfig::fiscal($endpoint);

        $req = Http::acceptJson()
            ->contentType('application/json')
            ->connectTimeout($t['connect_timeout'])
            ->timeout($t['timeout']);

        if (! $t['verify_ssl']) {
            $req = $req->withoutVerifying();
        }

        return $req;
    }
}
