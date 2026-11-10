<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | NUMART ERP (D-31) — pengganti koneksi DB `numart` (POS lama). Dipakai saat NUMART_SOURCE=erp.
    | branch_codes: nomor cabang POS lama (dipakai di seluruh aplikasi ini) → kode cabang ERP; id ERP dicari lewat GET /integrasi/cabang.
    | branch_map (opsional, "0:1,1:2,5:6") menimpa pencarian kode bila perlu.
    */
    'numart_erp' => [
        'url' => env('NUMART_ERP_URL'),
        'client_id' => env('NUMART_ERP_CLIENT_ID'),
        'secret' => env('NUMART_ERP_CLIENT_SECRET'),
        'branch_codes' => [0 => 'GDG', 1 => 'DKN', 2 => 'PKS', 3 => 'SRB', 4 => 'BQN', 5 => 'TGR', 6 => 'RTR'],
        'branch_map' => env('NUMART_ERP_BRANCH_MAP', ''),
        'reserve_minutes' => (int) env('NUMART_ERP_RESERVE_MINUTES', 120),
        'cache_seconds' => (int) env('NUMART_ERP_CACHE_SECONDS', 60),
    ],

];
