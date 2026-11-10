<?php

namespace App\Services\Erp;

use App\Services\NumartErpClient;
use Illuminate\Support\Facades\Cache;

/**
 * Pengaturan belanja online dari Backoffice ERP (O-4): GET /integrasi/pengaturan
 * → {pengaturan: {min_order_retail, min_order_grosir}, diskon: [{barang_id, kode, cabang_id, tipe, nilai, mulai, selesai}]}.
 * Di-cache singkat (sama dengan cache 30 detik versi POS lama).
 */
class ErpSettings
{
    private const CACHE_KEY = 'numart_erp.pengaturan';

    public function __construct(private NumartErpClient $erp) {}

    /** @return array{pengaturan: array<string, string>, diskon: list<array<string, mixed>>} */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, 30, fn () => $this->erp->settings());
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
