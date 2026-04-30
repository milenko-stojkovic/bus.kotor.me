<?php

namespace App\Services\Pdf;

use Barryvdh\DomPDF\Facade\Pdf;
use RuntimeException;

class PanelStatisticsPdfGenerator
{
    /**
     * @param  array<string, mixed>  $dataset
     */
    public function renderBinary(array $dataset): string
    {
        $previousLocale = app()->getLocale();
        app()->setLocale('cg');

        try {
            $pdf = Pdf::loadView('pdf.panel-statistics-report', [
                'dataset' => $dataset,
                'logoDataUri' => KotorPdfAssets::logoDataUri(),
            ])->setPaper('a4', 'portrait');

            $out = $pdf->output();
            if (! is_string($out) || $out === '') {
                throw new RuntimeException('Panel statistics PDF output empty.');
            }

            return $out;
        } finally {
            app()->setLocale($previousLocale);
        }
    }
}

