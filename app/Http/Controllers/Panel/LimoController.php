<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\LimoQrToken;
use App\Services\Limo\LimoQrService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class LimoController extends Controller
{
    public function index(Request $request, LimoQrService $limoQrService): View
    {
        $userId = (int) $request->user()->id;

        return view('panel.limo.index', [
            'tokens' => $limoQrService->listActiveTokensForToday($userId),
            'slotsUsedToday' => $limoQrService->countQrSlotsUsedToday($userId),
            'slotsMax' => LimoQrService::MAX_ACTIVE_GENERATIONS_PER_DAY,
        ]);
    }

    public function generateQr(Request $request, LimoQrService $limoQrService): RedirectResponse
    {
        $userId = (int) $request->user()->id;

        try {
            $result = $limoQrService->generateForAgency($userId);
        } catch (\RuntimeException $e) {
            $toIndex = fn () => redirect()->route('panel.limo.index');

            return match ($e->getMessage()) {
                'limit' => $toIndex()->withErrors([
                    'generate' => __('Dostignut je dnevni limit od :max QR kodova za ovaj datum.', ['max' => LimoQrService::MAX_ACTIVE_GENERATIONS_PER_DAY]),
                ]),
                'insufficient_advance' => $toIndex()->withErrors([
                    'generate' => __('Stanje avansa nije dovoljno za generisanje QR koda (minimalno :amount EUR).', ['amount' => \App\Services\Limo\LimoPickupService::AMOUNT_EUR]),
                ]),
                default => throw $e,
            };
        }

        return redirect()
            ->route('panel.limo.index')
            ->with('limo_new_qr_token', $result['raw_token']);
    }

    public function showQr(Request $request, LimoQrToken $limoQrToken, LimoQrService $limoQrService): View
    {
        $userId = (int) $request->user()->id;
        if ($limoQrToken->agency_user_id !== $userId) {
            abort(403);
        }

        $today = Carbon::today('Europe/Podgorica');
        if (! $limoQrToken->valid_on || ! $limoQrToken->valid_on->isSameDay($today)) {
            abort(404);
        }

        try {
            $raw = $limoQrService->decryptRawPayload($limoQrToken);
        } catch (\Throwable) {
            abort(404);
        }

        return view('panel.limo.show', [
            'token' => $limoQrToken,
            'qrDataUri' => LimoQrService::qrImageDataUri($raw),
        ]);
    }
}
