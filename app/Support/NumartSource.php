<?php

namespace App\Support;

/**
 * Saklar transisi D-31: dari mana data toko (katalog, stok, member, nota, kurir) dibaca/ditulis.
 *   NUMART_SOURCE=pos → koneksi DB `numart` (POS lama) — perilaku lama, default.
 *   NUMART_SOURCE=erp → API NUMART ERP lewat App\Services\NumartErpClient.
 * Setelah cutover 30 Nov 2026 DB POS lama hanya-baca, jadi produksi wajib `erp`. Rollback = kembali ke `pos`.
 */
final class NumartSource
{
    public const POS = 'pos';

    public const ERP = 'erp';

    public static function current(): string
    {
        return strtolower(trim((string) config('marketplace.numart_source', self::POS))) === self::ERP ? self::ERP : self::POS;
    }

    public static function erp(): bool
    {
        return self::current() === self::ERP;
    }

    public static function pos(): bool
    {
        return ! self::erp();
    }
}
