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

        // Upper bound for total wall time across all variants and PSM passes (seconds).
        'max_total_seconds' => (float) env('LIMO_OCR_MAX_TOTAL_SECONDS', 15),

        // Max seconds for a single Tesseract invocation (capped for field use).
        'per_pass_timeout_seconds' => (int) env('LIMO_OCR_PER_PASS_TIMEOUT', 3),

        // Stop trying more variants/PSMs when best candidate score reaches this (or strict ME pattern).
        'early_exit_min_score' => (int) env('LIMO_OCR_EARLY_EXIT_MIN_SCORE', 500),

        // When APP_DEBUG is true: keep copies of variant images under storage/app/private/limo_ocr_debug/{suffix}/ for local inspection (no public URLs).
        'debug_save_images' => filter_var(env('LIMO_OCR_DEBUG_SAVE_IMAGES', false), FILTER_VALIDATE_BOOL),
        'debug_image_ttl_minutes' => (int) env('LIMO_OCR_DEBUG_IMAGE_TTL_MINUTES', 60),

        // With APP_DEBUG: extra PSM modes (6,11,13) and whitelist off passes — many more Tesseract calls; keep false in production.
        'debug_extended_attempts' => filter_var(env('LIMO_OCR_DEBUG_EXTENDED_ATTEMPTS', false), FILTER_VALIDATE_BOOL),
    ],
];

