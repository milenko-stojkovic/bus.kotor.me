<?php

namespace App\Services;

use App\Models\Reservation;

/**
 * Fiskalizacija rezervacije. Vraća ['fiscal_jir' => ..., ...] na uspeh ili ['error' => message] na neuspeh.
 */
class FiscalizationService
{
    /**
     * Poziv fiskalnog API-ja. Na uspeh vraća niz sa fiscal_jir (i ostalim poljima); na neuspeh ['error' => string].
     */
    public function tryFiscalize(Reservation $reservation): array
    {
        // TODO: real fiscal API call; on failure return ['error' => $message]
        return [
            'fiscal_jir' => 'JIR-'.uniqid(),
            'fiscal_ikof' => 'IKOF-'.uniqid(),
            'fiscal_qr' => null,
            'fiscal_operator' => config('app.name'),
            'fiscal_date' => now(),
        ];
    }
}
