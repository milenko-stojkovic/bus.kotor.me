<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SAMO za test: simulacija fiskalnog servisa.
 *
 * POST /fake-fiscalization – legacy fake endpoint.
 *
 * Usklađeno sa real provider shape-om: vraća isti JSON oblik kao /api/efiscal/fiscalReceipt.
 * (Zadržano radi backward compat sa starijim pozivima.)
 */
class FakeFiscalizationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        // Delegate to FakeFiscalApiController receipt response for consistent contract.
        return app(FakeFiscalApiController::class)->fiscalReceipt($request);
    }
}
