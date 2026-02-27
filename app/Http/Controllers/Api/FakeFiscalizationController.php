<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * SAMO za test: simulacija fiskalnog servisa.
 *
 * POST /fake-fiscalization – prihvata payload identičan onom koji se šalje realnom servisu.
 * Ako u requestu postoji forceFail=true (body ili query), vraća JSON grešku.
 * Inače vraća success: status=OK, jir=FAKE-JIR-{uuid}, message="Fiskalizacija uspešna".
 * Ne dira postojeći kod fiskalizacije – ovo je samo fake servis.
 */
class FakeFiscalizationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if ($request->boolean('forceFail')) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Simulirana greška fiskalizacije (forceFail=true).',
            ], 422);
        }

        return response()->json([
            'status' => 'OK',
            'jir' => 'FAKE-JIR-'.Str::uuid()->toString(),
            'message' => 'Fiskalizacija uspešna',
        ]);
    }
}
