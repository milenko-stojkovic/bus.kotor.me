<?php

namespace App\Services\Pdf;

use App\Models\AgencyAdvanceTopup;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use RuntimeException;

final class AdvanceTopupConfirmationPdfGenerator
{
    public function renderBinary(User $agency, AgencyAdvanceTopup $topup, string $balanceAfter): string
    {
        $pdf = Pdf::loadView('pdf.advance-topup-confirmation', [
            'agency' => $agency,
            'topup' => $topup,
            'balanceAfter' => $balanceAfter,
            'logoDataUri' => KotorPdfAssets::logoDataUri(),
        ])->setPaper('a4', 'portrait');

        $out = $pdf->output();
        if (! is_string($out) || $out === '') {
            throw new RuntimeException('Advance topup confirmation PDF output empty.');
        }

        return $out;
    }
}

