<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin preview cache (MEGA re-download to disk)
    |--------------------------------------------------------------------------
    |
    | When an archived private file is missing locally, admin preview may
    | re-download it temporarily. files:cleanup-preview-cache removes those
    | files after preview_expires_at (TTL from restore time).
    |
    */
    'preview_ttl_minutes' => (int) env('EXTERNAL_ARCHIVE_PREVIEW_TTL_MINUTES', 60),

];
