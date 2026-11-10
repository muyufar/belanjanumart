<?php

namespace App\Services\Erp;

use App\Services\NumartErpClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Katalog dari ERP (GET /integrasi/katalog, /integrasi/kategori) dalam bentuk baris yang sama dengan tabel `barang`
 * POS lama, supaya CatalogService::mapProductRow, PricingService, keranjang, dan view tidak berubah.
 *
 * Kunci barang lintas sistem = kode (ERP products.code = barang_kode POS lama). `barang_id` pada baris ini = id barang ERP.
 * ERP hanya mengirim harga satu tipe per panggilan; untuk harga coret member (PricingService::comparePrice) harga umum
 * diambil dengan panggilan kedua. HPP (barang_harga_beli) TIDAK dibuka ERP ke mitra → selalu null (lihat TODO O-5 di ShippingService).
 */
class ErpCatalog
{
    public const TIERS = [0 => 'umum', 1 => 'retail', 2 => 'grosir'];

    public function __construct(private NumartErpClient $erp, private ErpBranchMap $branches) {}

    public static function tierName(int $tier): string
    {
        return self::TIERS[$tier] ?? 'umum';
    }

    /**
     * @param  array<string, mixed>  $params  q, kategori_id, kode, id, urut, terlaris_hari, hanya_ada_stok
     * @param  list<int>  $stockPosCabangs  cabang stok (nomor POS) — stok dijumlah di ERP
     * @return array{rows: Collection<int, object>, total: int}
     */
    public function search(int $posCabang, int $tier, array $params, array $stockPosCabangs, int $page = 1, int $perPage = 20): array
    {
        $erpCabang = $this->branches->toErp($posCabang);
        $stock = $this->branches->toErpList($stockPosCabangs !== [] ? $stockPosCabangs : [$posCabang]);
        $extra = array_filter($params + [
            'per_halaman' => max(1, min(100, $perPage)),
            'stok_cabang_ids' => $stock !== [] ? implode(',', $stock) : null,
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
        $q = $extra['q'] ?? null;
        unset($extra['q']);

        $data = $this->cached([$erpCabang, $tier, $q, $page, $extra], fn () => $this->erp->catalog($erpCabang, self::tierName($tier), $q, max(1, $page), $extra));
        $items = $data['barang'] ?? [];

        $umum = [];
        if ($tier > 0 && $items !== []) {
            $ids = array_map(fn ($b) => (int) $b['id'], $items);
            $res = $this->cached([$erpCabang, 'umum', $ids], fn () => $this->erp->catalog($erpCabang, 'umum', null, 1, ['id' => implode(',', $ids), 'per_halaman' => count($ids)]));
            foreach ($res['barang'] ?? [] as $b) {
                $umum[(int) $b['id']] = self::levelOne($b)['harga'] ?? null;
            }
        }

        $rows = collect($items)->map(fn (array $b) => $this->toRow($b, $tier, $umum[(int) $b['id']] ?? null, $posCabang));

        return ['rows' => $rows, 'total' => (int) ($data['total'] ?? $rows->count())];
    }

    /** @return Collection<int, object> bentuk tabel `kategori` POS lama */
    public function categories(int $posCabang): Collection
    {
        $erpCabang = $this->branches->toErp($posCabang);

        return collect($this->cached(['kategori', $erpCabang], fn () => $this->erp->categories($erpCabang)))
            ->map(fn (array $k) => (object) [
                'kategori_id' => (int) $k['id'],
                'kategori_nama' => (string) $k['nama'],
                'kategori_cabang' => $posCabang,
                'kategori_status' => '1',
                'jumlah_barang' => (int) ($k['jumlah_barang'] ?? 0),
            ]);
    }

    /** @param array<string, mixed> $b barang dari /integrasi/katalog */
    public function toRow(array $b, int $tier, $umumPrice, int $posCabang): object
    {
        $unit = self::levelOne($b);
        $price = isset($unit['harga']) ? (float) $unit['harga'] : null;

        return (object) [
            'barang_id' => (int) $b['id'],
            'barang_kode' => (string) $b['kode'],
            'barang_nama' => (string) $b['nama'],
            'barang_harga' => $tier === 0 ? $price : ($umumPrice !== null ? (float) $umumPrice : null),
            'barang_harga_grosir_1' => $tier === 1 ? $price : null,
            'barang_harga_grosir_2' => $tier === 2 ? $price : null,
            'barang_harga_beli' => null,   // ERP tidak membuka HPP — O-5
            'barang_stock' => (float) ($b['stok_tersedia_dasar'] ?? 0),
            'barang_gambar' => $b['gambar'] ?? null,
            'barang_tanggal' => $b['dibuat'] ?? null,
            'barang_cabang' => $posCabang,
            'barang_status' => '1',
            'satuan_id' => 0,
            'satuan_isi_1' => max(1, (int) round((float) ($unit['isi'] ?? 1))),
            'satuan_nama' => $unit['nama'] ?? null,
            'erp_level' => (int) ($unit['level'] ?? 1),
            'kategori_id' => isset($b['kategori_id']) ? (int) $b['kategori_id'] : null,
            'kategori_nama' => $b['kategori'] ?? null,
            'terjual' => isset($b['terjual']) ? (float) $b['terjual'] : null,
        ];
    }

    /** Satuan jual online = level 1 (satuan dasar), sama dengan POS lama yang menjual per barang_harga. */
    public static function levelOne(array $b): array
    {
        $units = $b['satuan'] ?? [];
        foreach ($units as $u) {
            if ((int) ($u['level'] ?? 0) === 1) {
                return $u;
            }
        }

        return $units[0] ?? [];
    }

    private function cached(array $key, \Closure $fn): array
    {
        $ttl = (int) config('services.numart_erp.cache_seconds', 60);
        if ($ttl <= 0) {
            return $fn();
        }

        return Cache::remember('numart_erp.katalog.'.md5(json_encode($key)), $ttl, $fn);
    }
}
