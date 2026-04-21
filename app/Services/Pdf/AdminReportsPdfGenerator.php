<?php

namespace App\Services\Pdf;

use Barryvdh\DomPDF\Facade\Pdf;
use RuntimeException;

class AdminReportsPdfGenerator
{
    /**
     * @param  array<string, mixed>  $dataset
     */
    public function renderBinary(array $dataset): string
    {
        $pdf = Pdf::loadView('pdf.admin-report', [
            'dataset' => $dataset,
            'logoDataUri' => KotorPdfAssets::logoDataUri(),
        ])->setPaper('a4', 'portrait');

        $out = $pdf->output();
        if (! is_string($out) || $out === '') {
            throw new RuntimeException('Report PDF output empty.');
        }

        return $out;
    }
}

