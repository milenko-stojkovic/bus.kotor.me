<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\LimoQrToken;
use App\Services\Limo\LimoPickupService;
use App\Services\Limo\LimoQrService;
use App\Services\Pdf\LimoQrPdfGenerator;
use App\Support\UiText;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
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

            $locale = app()->getLocale();

            return match ($e->getMessage()) {
                'limit' => $toIndex()->withErrors([
                    'generate' => str_replace(
                        ':max',
                        (string) LimoQrService::MAX_ACTIVE_GENERATIONS_PER_DAY,
                        UiText::t(
                            'panel',
                            'limo_generate_error_limit',
                            $locale === 'en'
                                ? 'The daily limit of :max QR codes for this date has been reached.'
                                : 'Dostignut je dnevni limit od :max QR kodova za ovaj datum.',
                            $locale,
                        ),
                    ),
                ]),
                'insufficient_advance' => $toIndex()->withErrors([
                    'generate' => str_replace(
                        ':amount',
                        (string) LimoPickupService::AMOUNT_EUR,
                        UiText::t(
                            'panel',
                            'limo_generate_error_insufficient_advance',
                            $locale === 'en'
                                ? 'Your advance balance is not enough to generate a QR code (minimum :amount EUR).'
                                : 'Stanje avansa nije dovoljno za generisanje QR koda (minimalno :amount EUR).',
                            $locale,
                        ),
                    ),
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

    public function qrPdf(
        Request $request,
        LimoQrToken $limoQrToken,
        LimoQrService $limoQrService,
        LimoQrPdfGenerator $pdfGenerator,
    ): StreamedResponse {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $userId = (int) $user->id;
        if ($limoQrToken->agency_user_id !== $userId) {
            abort(404);
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

        $binary = $pdfGenerator->renderBinary($limoQrToken, $raw, $user);
        abort_if($binary === '', 404);

        $filename = sprintf('limo-qr-%d-%s.pdf', (int) $limoQrToken->id, $today->format('Y-m-d'));

        return response()->streamDownload(
            static function () use ($binary): void {
                echo $binary;
            },
            $filename,
            [
                'Content-Type' => 'application/pdf',
            ]
        );
    }
}
