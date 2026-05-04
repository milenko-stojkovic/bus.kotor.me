<?php

namespace App\Services\Limo;

use Illuminate\Support\Facades\Log;

/**
 * Advisory OCR only — never authoritative. Currently stubbed (returns null); integrate Tesseract/API later.
 */
final class LimoPlateOcrService
{
    /**
     * @return string|null raw suggested text for plate normalization upstream, or null if unavailable / uncertain
     */
    public function suggestPlate(string $absoluteImagePath): ?string
    {
        Log::channel('payments')->info('limo_plate_ocr_attempted', [
            'integrated' => false,
            'path_suffix' => basename($absoluteImagePath),
        ]);

        return null;
    }
}
