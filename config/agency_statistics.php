<?php

return [

    /*
    |--------------------------------------------------------------------------
    | V1 cut-over timestamp
    |--------------------------------------------------------------------------
    |
    | Reservations with user_id = null and created_at strictly before this
    | moment are treated as the unmigrated V1 historical pool for heuristic
    | reconstruction on the agency detail page. Post-cut-over guest reservations
    | are excluded from that pool.
    |
    */
    'v1_cutover_at' => env('AGENCY_STATS_V1_CUTOVER_AT', '2026-06-19 00:00:00'),

    /*
    |--------------------------------------------------------------------------
    | Heuristic cache TTL (seconds)
    |--------------------------------------------------------------------------
    */
    'heuristic_cache_ttl' => (int) env('AGENCY_STATS_HEURISTIC_CACHE_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | Name similarity threshold (0–100, similar_text percent)
    |--------------------------------------------------------------------------
    */
    'name_similarity_threshold' => 80,

];
