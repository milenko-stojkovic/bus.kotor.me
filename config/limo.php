<?php

return [
    'ocr' => [
        // Advisory only. If disabled/unavailable, suggested_plate remains null and manual entry is required.
        'enabled' => filter_var(env('LIMO_OCR_ENABLED', false), FILTER_VALIDATE_BOOL),

        // Optional explicit path to tesseract binary (e.g. /usr/bin/tesseract or C:\Program Files\Tesseract-OCR\tesseract.exe).
        // If empty, "tesseract" is used from PATH.
        'tesseract_binary' => env('LIMO_OCR_TESSERACT_BINARY'),

        // OCR attempt timeout (seconds). On timeout/failure we return null and continue manual flow.
        'timeout_seconds' => (int) env('LIMO_OCR_TIMEOUT_SECONDS', 10),
    ],
];

