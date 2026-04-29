<?php

namespace App\Services\Pdf;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use RuntimeException;

final class AgencyAdvanceYearlyStatementPdfGenerator
{
    /**
     * @param  list<array{date:string,type:string,description:string,amount:float,balance_after:float}>  $rows
     * @param  array{topup_total:float,usage_total:float,correction_total:float}  $totals
     */
    public function renderBinary(
        User $agency,
        int $year,
        float $openingBalance,
        array $rows,
        array $totals,
        float $closingBalance,
    ): string {
        $pdf = Pdf::loadView('pdf.agency-advance-yearly-statement', [
            'agency' => $agency,
            'year' => $year,
            'openingBalance' => $openingBalance,
            'rows' => $rows,
            'totals' => $totals,
            'closingBalance' => $closingBalance,
            'logoDataUri' => KotorPdfAssets::logoDataUri(),
        ])->setPaper('a4', 'portrait');

        $out = $pdf->output();
        if (! is_string($out) || $out === '') {
            throw new RuntimeException('Agency advance yearly statement PDF output empty.');
        }

        return $out;
    }
}

