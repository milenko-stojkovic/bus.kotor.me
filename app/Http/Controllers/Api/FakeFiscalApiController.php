<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Fake eksterni fiskal servis – samo za test.
 *
 * Rute: POST /api/efiscal/deposit, POST /api/efiscal/fiscalReceipt.
 * - forceFail=true (query/JSON) ili header X-Fake-Fail: 1 → Error payload.
 * - Header X-Fake-Timeout: 1 → sleep 10s, zatim 504.
 * Inače success. Ne koristi bazu.
 */
class FakeFiscalApiController extends Controller
{
    /**
     * POST /api/efiscal/deposit – simulacija inicijacije depozita.
     */
    public function deposit(Request $request): JsonResponse
    {
        return $this->respond($request);
    }

    /**
     * POST /api/efiscal/fiscalReceipt – simulacija zahteva za fiskalni račun.
     */
    public function fiscalReceipt(Request $request): JsonResponse
    {
        return $this->respond($request);
    }

    private function respond(Request $request): JsonResponse
    {
        if ($request->header('X-Fake-Timeout') === '1') {
            sleep(10);
            return response()->json([
                'IsSucccess' => false,
                'Error' => [
                    'ErrorCode' => '504',
                    'ErrorMessage' => 'Fake timeout (gateway timeout)',
                ],
            ], 504);
        }

        if ($request->header('X-Fake-Fail') === '1') {
            return response()->json([
                'IsSucccess' => false,
                'Error' => [
                    'ErrorCode' => '500',
                    'ErrorMessage' => 'Fake fiskal service error',
                ],
            ], 422);
        }

        $forceFail = $request->boolean('forceFail')
            || ($request->json('forceFail') === true);

        if ($forceFail) {
            return response()->json([
                'IsSucccess' => false,
                'Error' => [
                    'ErrorCode' => '500',
                    'ErrorMessage' => 'Fake fiskal service error',
                ],
            ], 422);
        }

        $uuid = Str::uuid()->toString();

        return response()->json([
            'IsSucccess' => true,
            'ResponseCode' => 'FAKE-JIR-'.$uuid,
            'UIDRequest' => 'FAKE-IKOF-'.$uuid,
            'Url' => [
                'Value' => 'https://fake.qr.local/qr/'.$uuid,
            ],
        ]);
    }
}
