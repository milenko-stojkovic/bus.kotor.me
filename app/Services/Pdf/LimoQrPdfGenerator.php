<?php

namespace App\Services\Pdf;

use App\Models\LimoQrToken;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use RuntimeException;

class LimoQrPdfGenerator
{
    public function renderBinary(LimoQrToken $token, string $raw, User $user): string
    {
        $previousLocale = app()->getLocale();
        $locale = $this->normalizeLocale((string) ($user->lang ?? 'cg'));
        app()->setLocale($locale);

        try {
            $qrDataUri = \App\Services\Limo\LimoQrService::qrImageDataUri($raw);

            $pdf = Pdf::loadView('pdf.limo-qr', [
                'token' => $token,
                'user' => $user,
                'qrDataUri' => $qrDataUri,
                'locale' => $locale,
            ])->setPaper('a4', 'portrait');

            $out = $pdf->output();
            if (! is_string($out) || $out === '') {
                throw new RuntimeException('Limo QR PDF output empty.');
            }

            return $out;
        } finally {
            app()->setLocale($previousLocale);
        }
    }

    private function normalizeLocale(string $locale): string
    {
        $l = strtolower(trim($locale));
        if ($l === 'en' || $l === 'cg') {
            return $l;
        }

        return 'cg';
    }
}

