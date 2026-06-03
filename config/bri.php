<?php

return [
    'sandbox' => env('BRI_SANDBOX', true),

    'base_url' => env('BRI_BASE_URL', env('BRI_SANDBOX', true)
        ? 'https://sandbox.partner.api.bri.co.id'
        : 'https://partner.api.bri.co.id'),

    'client_id' => env('BRI_CLIENT_ID', ''),
    'client_secret' => env('BRI_CLIENT_SECRET', ''),

    'institution_code' => env('BRI_INSTITUTION_CODE', ''),
    'briva_no' => env('BRI_BRIVA_NO', ''),

    /** Jam kedaluwarsa VA */
    'va_expire_hours' => (int) env('BRI_VA_EXPIRE_HOURS', 24),

    /**
     * Mode mock: buat VA lokal tanpa hit API (development tanpa kredensial).
     */
    'mock' => env('BRI_MOCK', true),

    'webhook_secret' => env('BRI_WEBHOOK_SECRET', ''),
];
