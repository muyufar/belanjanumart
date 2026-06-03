<?php

return [
    'name' => env('MARKETPLACE_NAME', 'Belanja Numart'),

    /** Nama kasir di laporan penjualan Numart */
    'kasir_label' => env('MARKETPLACE_KASIR_LABEL', 'Belanja Online'),

    /**
     * ID user kasir Numart (opsional). Jika kosong/tidak valid,
     * sistem membuat/mencari user bernama `kasir_label`.
     */
    'numart_kasir_id' => (int) env('NUMART_MARKETPLACE_KASIR_ID', 0),

    /** customer_id = 1 = "Dari Marketplace" di Numart */
    'numart_marketplace_customer_id' => 1,

    /** Menit reserve stok saat checkout */
    'stock_hold_minutes' => (int) env('MARKETPLACE_STOCK_HOLD_MINUTES', 15),

    /** Ongkir flat tahap 1 (Rp) */
    'default_shipping_fee' => (int) env('MARKETPLACE_SHIPPING_FEE', 10000),

    /**
     * Cabang fulfillment marketplace.
     * Koordinat contoh — sesuaikan di .env atau DB `fulfillment_branches`.
     */
    'branches' => [
        0 => [
            'id' => 0,
            'name' => 'Gudang Nugrasir',
            'slug' => 'gudang',
            'lat' => (float) env('BRANCH_GUDANG_LAT', -7.4706),
            'lng' => (float) env('BRANCH_GUDANG_LNG', 110.2170),
            'radius_km' => (float) env('BRANCH_GUDANG_RADIUS_KM', 9999),
            'priority' => 99,
        ],
        1 => [
            'id' => 1,
            'name' => 'Numart Dukun',
            'slug' => 'dukun',
            'lat' => (float) env('BRANCH_DUKUN_LAT', -7.6050),
            'lng' => (float) env('BRANCH_DUKUN_LNG', 110.3160),
            'radius_km' => (float) env('BRANCH_DUKUN_RADIUS_KM', 12),
            'priority' => 1,
        ],
        5 => [
            'id' => 5,
            'name' => 'Numart Tegalrejo',
            'slug' => 'tegalrejo',
            'lat' => (float) env('BRANCH_TEGALREJO_LAT', -7.5120),
            'lng' => (float) env('BRANCH_TEGALREJO_LNG', 110.2400),
            'radius_km' => (float) env('BRANCH_TEGALREJO_RADIUS_KM', 12),
            'priority' => 2,
        ],
    ],

    /** Cabang yang diprioritaskan untuk deteksi jarak (selain gudang) */
    'nearby_branch_ids' => [1, 5],

    'catalog_cabang_display' => 0,

    /** Jumlah produk per halaman (katalog & produk terkait) */
    'products_per_page' => (int) env('MARKETPLACE_PRODUCTS_PER_PAGE', 20),

    /** Produk terkait per halaman di detail produk */
    'related_products_per_page' => (int) env('MARKETPLACE_RELATED_PER_PAGE', 12),

    'numart_asset_url' => env('NUMART_ASSET_URL', 'http://numart.test'),

    /** Cabang yang dihitung untuk ketersediaan stok katalog */
    'stock_branch_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('MARKETPLACE_STOCK_BRANCH_IDS', '0,1,5'))
    ))),

    'home_best_sellers_limit' => (int) env('MARKETPLACE_HOME_BEST_SELLERS', 8),
    'home_latest_limit' => (int) env('MARKETPLACE_HOME_LATEST', 8),
    'home_discount_limit' => (int) env('MARKETPLACE_HOME_DISCOUNT', 8),

    /** Hari ke belakang untuk hitung Produk Terlaris */
    'best_sellers_days' => (int) env('MARKETPLACE_BEST_SELLERS_DAYS', 7),

    /** Endpoint POST api/marketplace-wa-send.php di Numart */
    'wa_api_url' => env('NUMART_WA_API_URL', ''),
    'wa_api_secret' => env('NUMART_WA_API_SECRET', ''),

    /**
     * Fallback: baca token Fonnte langsung dari POS (api/no.js).
     * Dipakai jika URL API kosong atau gagal.
     */
    'fonnte_no_js_path' => env('NUMART_FONNTE_NO_JS_PATH', 'C:/laragon/www/numart/api/no.js'),

    /** 1 poin per Rp berapa (selaras customer-zoom.php Numart) */
    'points_per_amount' => (int) env('MARKETPLACE_POINTS_PER_AMOUNT', 100_000),
];
