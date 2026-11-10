<?php

namespace App\Services;

use App\Services\Erp\ErpCatalog;
use App\Support\NumartSource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class CatalogService
{
    public const TIPE_TERBARU = 'terbaru';

    public const TIPE_TERLARIS = 'terlaris';

    /** @var list<string> */
    public const SPECIAL_TIPES = [self::TIPE_TERBARU, self::TIPE_TERLARIS];

    public function __construct(
        protected PricingService $pricing,
        protected MarketplaceDiscountService $discounts,
    ) {}

    /** @return list<int> */
    public function stockBranchIds(?int $memberCabang = null): array
    {
        if ($memberCabang !== null) {
            return [$memberCabang];
        }

        $ids = config('marketplace.stock_branch_ids');
        if (is_array($ids) && $ids !== []) {
            return array_map('intval', $ids);
        }

        return array_merge(
            [(int) config('marketplace.catalog_cabang_display', 0)],
            array_map('intval', config('marketplace.nearby_branch_ids', [1, 5]))
        );
    }

    /** Kategori master selalu dari cabang pusat (0). */
    public function categoriesFromPusat(?int $limit = 50): Collection
    {
        return $this->categories((int) config('marketplace.catalog_cabang_display', 0), $limit);
    }

    public function categories(int $cabangId = 0, ?int $limit = 50): Collection
    {
        if (NumartSource::erp()) {
            $cats = app(ErpCatalog::class)->categories($cabangId);

            return $limit !== null && $limit > 0 ? $cats->take($limit)->values() : $cats;
        }

        $q = DB::connection('numart')
            ->table('kategori')
            ->where('kategori_status', '1')
            ->where('kategori_cabang', $cabangId)
            ->orderBy('kategori_nama');

        if ($limit !== null && $limit > 0) {
            $q->limit($limit);
        }

        return $q->get();
    }

    public function categoryById(int $kategoriId, int $cabangId = 0): ?object
    {
        if ($kategoriId < 1) {
            return null;
        }

        $cabangId = $cabangId > 0 ? $cabangId : (int) config('marketplace.catalog_cabang_display', 0);

        if (NumartSource::erp()) {
            return app(ErpCatalog::class)->categories($cabangId)->firstWhere('kategori_id', $kategoriId);
        }

        return DB::connection('numart')
            ->table('kategori')
            ->where('kategori_id', $kategoriId)
            ->where('kategori_status', '1')
            ->where('kategori_cabang', $cabangId)
            ->first();
    }

    public function products(int $cabangId, int $tier, ?string $search = null, ?int $kategoriId = null, int $limit = 24, int $offset = 0): Collection
    {
        return $this->paginateProducts($cabangId, $tier, $search, $kategoriId, $limit, 'page', null, $offset)
            ->getCollection();
    }

    public function paginateProducts(
        int $cabangId,
        int $tier,
        ?string $search = null,
        ?int $kategoriId = null,
        ?int $perPage = null,
        string $pageName = 'page',
        ?int $excludeBarangId = null,
        ?int $forcedOffset = null,
        ?int $memberCabang = null,
        ?CatalogProductFilters $filters = null,
    ): LengthAwarePaginator {
        $perPage = $perPage ?? (int) config('marketplace.products_per_page', 20);
        $filters ??= new CatalogProductFilters;
        if (NumartSource::erp()) {
            return $this->erpPaginate($cabangId, $tier, $this->erpParams($filters, $search, $kategoriId), $perPage, $pageName, $memberCabang, $forcedOffset, $excludeBarangId);
        }
        $query = $this->productsQuery($cabangId, $search, $kategoriId, $excludeBarangId, $memberCabang);
        $this->applyProductFilters($query, $filters, $memberCabang);
        $this->applyProductSort($query, $filters, $tier, $memberCabang);

        if ($forcedOffset !== null) {
            $total = (clone $query)->count();
            $items = $this->fetchAndMapRows($query, $tier, $forcedOffset, $perPage, $memberCabang);
            $currentPage = (int) floor($forcedOffset / $perPage) + 1;

            return new LengthAwarePaginator(
                $items,
                $total,
                $perPage,
                $currentPage,
                ['path' => Request::url(), 'pageName' => $pageName]
            );
        }

        return $this->paginateFromQuery($query, $tier, $perPage, $pageName, $memberCabang);
    }

    /**
     * @param  list<string>  $kodes
     */
    public function productsByKodes(int $cabangId, int $tier, array $kodes, int $limit = 12, ?int $memberCabang = null): Collection
    {
        $kodes = array_values(array_filter(array_unique($kodes)));
        if ($kodes === []) {
            return collect();
        }

        if (NumartSource::erp()) {
            $order = array_flip($kodes);

            return $this->erpProducts($cabangId, $tier, ['kode' => implode(',', array_slice($kodes, 0, 100))], $memberCabang, min(100, count($kodes)))
                ->sortBy(fn ($p) => $order[$p->barang_kode] ?? 9999)
                ->values()
                ->take($limit);
        }

        $query = $this->productsQuery($cabangId, null, null, null, $memberCabang)
            ->whereIn('b.barang_kode', $kodes);

        $rows = $query->get();
        $discountMap = $this->discounts->activeDiscountsByKode();

        $mapped = $rows->map(fn ($row) => $this->mapProductRow($row, $tier, $discountMap));

        $order = array_flip($kodes);

        return $mapped
            ->sortBy(fn ($p) => $order[$p->barang_kode] ?? 9999)
            ->values()
            ->take($limit);
    }

    public function bestSellersThisWeek(int $cabangId, int $tier, ?int $limit = null): Collection
    {
        $limit = $limit ?? (int) config('marketplace.home_best_sellers_limit', 8);

        return $this->bestSellingProducts($cabangId, $tier, $limit);
    }

    public function bestSellingProducts(int $cabangId, int $tier, ?int $limit = null, ?int $memberCabang = null): Collection
    {
        $limit = $limit ?? (int) config('marketplace.home_best_sellers_limit', 8);
        if (NumartSource::erp()) {
            return $this->erpProducts($cabangId, $tier, ['urut' => 'terlaris', 'terlaris_hari' => (int) config('marketplace.best_sellers_days', 7)], $memberCabang, $limit);
        }
        $topKodes = $this->topSellingKodes($limit * 3, $memberCabang);

        return $this->productsByKodes($cabangId, $tier, $topKodes, $limit, $memberCabang);
    }

    public function latestProducts(int $cabangId, int $tier, ?int $limit = null, ?int $memberCabang = null): Collection
    {
        $limit = $limit ?? (int) config('marketplace.home_latest_limit', 8);
        if (NumartSource::erp()) {
            return $this->erpProducts($cabangId, $tier, ['urut' => 'terbaru'], $memberCabang, $limit);
        }
        $query = $this->productsQuery($cabangId, null, null, null, $memberCabang)
            ->orderByDesc('b.barang_tanggal')
            ->orderByDesc('b.barang_id');

        $rows = $query->limit($limit)->get();
        $discountMap = $this->discounts->activeDiscountsByKode();
        $kodes = $rows->pluck('barang_kode')->all();
        $stockTotals = $this->stockTotalsByKode($kodes, $memberCabang);

        return $rows->map(function ($row) use ($tier, $discountMap, $stockTotals) {
            $row->barang_stock = $stockTotals[$row->barang_kode] ?? (float) $row->barang_stock;

            return $this->mapProductRow($row, $tier, $discountMap);
        });
    }

    public function paginateLatestProducts(
        int $cabangId,
        int $tier,
        ?string $search = null,
        ?int $perPage = null,
        string $pageName = 'page',
        ?int $memberCabang = null,
        ?CatalogProductFilters $filters = null,
    ): LengthAwarePaginator {
        $filters ??= new CatalogProductFilters(sort: CatalogProductFilters::SORT_TERBARU);
        if (NumartSource::erp()) {
            return $this->erpPaginate($cabangId, $tier, $this->erpParams($filters, $search, null), $perPage ?? (int) config('marketplace.products_per_page', 20), $pageName, $memberCabang);
        }
        $query = $this->productsQuery($cabangId, $search, null, null, $memberCabang);
        $this->applyProductFilters($query, $filters, $memberCabang);
        $this->applyProductSort($query, $filters, $tier, $memberCabang);

        return $this->paginateFromQuery($query, $tier, $perPage, $pageName, $memberCabang);
    }

    public function paginateBestSellingProducts(
        int $cabangId,
        int $tier,
        ?string $search = null,
        ?int $perPage = null,
        string $pageName = 'page',
        ?int $memberCabang = null,
        ?CatalogProductFilters $filters = null,
    ): LengthAwarePaginator {
        $filters ??= new CatalogProductFilters(sort: CatalogProductFilters::SORT_TERLARIS);
        if (NumartSource::erp()) {
            return $this->erpPaginate($cabangId, $tier, $this->erpParams($filters, $search, null), $perPage ?? (int) config('marketplace.products_per_page', 20), $pageName, $memberCabang);
        }
        $salesSub = $this->salesByKodeSubquery($memberCabang);
        $query = $this->productsQuery($cabangId, $search, null, null, $memberCabang);
        $this->applyProductFilters($query, $filters, $memberCabang);

        if ($filters->sort === CatalogProductFilters::SORT_TERLARIS) {
            $query->joinSub($salesSub, 'bs', function ($join) {
                $join->on('bs.barang_kode', '=', 'b.barang_kode');
            })->orderByDesc('bs.sold_qty');
        } else {
            $this->applyProductSort($query, $filters, $tier, $memberCabang);
        }

        return $this->paginateFromQuery($query, $tier, $perPage, $pageName, $memberCabang);
    }

    /**
     * @return list<string>
     */
    protected function topSellingKodes(int $limit, ?int $memberCabang = null): array
    {
        return $this->salesByKodeSubquery($memberCabang)
            ->orderByDesc('sold_qty')
            ->limit($limit)
            ->pluck('barang_kode')
            ->all();
    }

    protected function salesByKodeSubquery(?int $memberCabang = null)
    {
        $days = (int) config('marketplace.best_sellers_days', 7);
        $since = now('Asia/Jakarta')->subDays($days)->toDateString();
        $branchIds = $this->stockBranchIds($memberCabang);

        return DB::connection('numart')
            ->table('penjualan as pj')
            ->join('barang as bx', 'bx.barang_id', '=', 'pj.penjualan_barang_id')
            ->where('pj.penjualan_date', '>=', $since)
            ->whereIn('pj.penjualan_cabang', $branchIds)
            ->groupBy('bx.barang_kode')
            ->selectRaw('bx.barang_kode as barang_kode, SUM(pj.barang_qty) as sold_qty');
    }

    protected function paginateFromQuery($query, int $tier, ?int $perPage, string $pageName, ?int $memberCabang = null): LengthAwarePaginator
    {
        $perPage = $perPage ?? (int) config('marketplace.products_per_page', 20);
        $total = (clone $query)->count();
        $currentPage = max(1, (int) Request::input($pageName, 1));
        $offset = ($currentPage - 1) * $perPage;
        $items = $this->fetchAndMapRows($query, $tier, $offset, $perPage, $memberCabang);

        $paginator = new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $currentPage,
            [
                'path' => Request::url(),
                'pageName' => $pageName,
            ]
        );

        return $paginator->appends(
            collect(Request::query())->except($pageName)->all()
        );
    }

    public function discountedProducts(int $cabangId, int $tier, ?int $limit = null, ?int $memberCabang = null): Collection
    {
        $limit = $limit ?? (int) config('marketplace.home_discount_limit', 8);

        if (! $this->discounts->tableExists()) {
            return collect();
        }

        $discountMap = $this->discounts->activeDiscountsByKode();
        if ($discountMap->isEmpty()) {
            return collect();
        }

        $kodes = $discountMap->keys()->take($limit * 3)->all();

        return $this->productsByKodes($cabangId, $tier, $kodes, $limit, $memberCabang)
            ->filter(fn ($p) => $p->has_discount ?? false)
            ->values();
    }

    protected function productsQuery(int $cabangId, ?string $search, ?int $kategoriId, ?int $excludeBarangId = null, ?int $memberCabang = null)
    {
        $q = DB::connection('numart')
            ->table('barang as b')
            ->leftJoin('kategori as k', 'k.kategori_id', '=', 'b.kategori_id')
            ->where('b.barang_cabang', $cabangId)
            ->where('b.barang_status', '1')
            ->select([
                'b.barang_id',
                'b.barang_kode',
                'b.barang_nama',
                'b.barang_harga',
                'b.barang_harga_grosir_1',
                'b.barang_harga_grosir_2',
                'b.barang_harga_beli',
                'b.barang_stock',
                'b.barang_gambar',
                'b.barang_tanggal',
                'b.satuan_id',
                'b.satuan_isi_1',
                'b.kategori_id',
                'k.kategori_nama',
            ]);

        if ($search) {
            $q->where(function ($w) use ($search) {
                $w->where('b.barang_nama', 'like', '%'.$search.'%')
                    ->orWhere('b.barang_kode', 'like', '%'.$search.'%');
            });
        }

        if ($kategoriId) {
            $q->where('b.kategori_id', $kategoriId);
        }

        if ($excludeBarangId) {
            $q->where('b.barang_id', '!=', $excludeBarangId);
        }

        return $q;
    }

    protected function applyProductFilters($query, CatalogProductFilters $filters, ?int $memberCabang): void
    {
        if ($filters->stok === 'ada') {
            $branchIds = $this->stockBranchIds($memberCabang);
            $query->whereExists(function ($sub) use ($branchIds) {
                $sub->from('barang as bs')
                    ->whereColumn('bs.barang_kode', 'b.barang_kode')
                    ->whereIn('bs.barang_cabang', $branchIds)
                    ->where('bs.barang_status', '1')
                    ->whereRaw('CAST(bs.barang_stock AS DECIMAL(12,2)) > 0');
            });
        } elseif ($filters->stok === 'habis') {
            $branchIds = $this->stockBranchIds($memberCabang);
            $query->whereNotExists(function ($sub) use ($branchIds) {
                $sub->from('barang as bs')
                    ->whereColumn('bs.barang_kode', 'b.barang_kode')
                    ->whereIn('bs.barang_cabang', $branchIds)
                    ->where('bs.barang_status', '1')
                    ->whereRaw('CAST(bs.barang_stock AS DECIMAL(12,2)) > 0');
            });
        }

        if ($filters->promo === 'diskon') {
            if (! $this->discounts->tableExists()) {
                $query->whereRaw('1 = 0');

                return;
            }

            $kodes = $this->discounts->activeDiscountsByKode()->keys()->all();
            if ($kodes === []) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->whereIn('b.barang_kode', $kodes);
        }
    }

    protected function applyProductSort($query, CatalogProductFilters $filters, int $tier, ?int $memberCabang): void
    {
        match ($filters->sort) {
            CatalogProductFilters::SORT_NAMA_DESC => $query->orderByDesc('b.barang_nama'),
            CatalogProductFilters::SORT_HARGA_ASC => $query->orderBy($this->priceColumnForTier($tier)),
            CatalogProductFilters::SORT_HARGA_DESC => $query->orderByDesc($this->priceColumnForTier($tier)),
            CatalogProductFilters::SORT_TERBARU => $query->orderByDesc('b.barang_tanggal')->orderByDesc('b.barang_id'),
            CatalogProductFilters::SORT_TERLARIS => $this->applyTerlarisSort($query, $memberCabang),
            default => $query->orderBy('b.barang_nama'),
        };
    }

    protected function priceColumnForTier(int $tier): string
    {
        return match ($tier) {
            1 => 'b.barang_harga_grosir_1',
            2 => 'b.barang_harga_grosir_2',
            default => 'b.barang_harga',
        };
    }

    protected function applyTerlarisSort($query, ?int $memberCabang): void
    {
        $salesSub = $this->salesByKodeSubquery($memberCabang);
        $query->leftJoinSub($salesSub, 'bs_sort', function ($join) {
            $join->on('bs_sort.barang_kode', '=', 'b.barang_kode');
        })->orderByDesc('bs_sort.sold_qty')->orderBy('b.barang_nama');
    }

    protected function fetchAndMapRows($query, int $tier, int $offset, int $limit, ?int $memberCabang = null): Collection
    {
        $rows = (clone $query)
            ->offset($offset)
            ->limit($limit)
            ->get();

        $discountMap = $this->discounts->activeDiscountsByKode();
        $kodes = $rows->pluck('barang_kode')->all();
        $stockTotals = $this->stockTotalsByKode($kodes, $memberCabang);

        return $rows->map(function ($row) use ($tier, $discountMap, $stockTotals) {
            $row->barang_stock = $stockTotals[$row->barang_kode] ?? (float) $row->barang_stock;

            return $this->mapProductRow($row, $tier, $discountMap);
        });
    }

    /**
     * @param  list<string>  $kodes
     * @return array<string, float>
     */
    protected function stockTotalsByKode(array $kodes, ?int $memberCabang = null): array
    {
        if ($kodes === []) {
            return [];
        }

        return DB::connection('numart')
            ->table('barang')
            ->whereIn('barang_kode', $kodes)
            ->whereIn('barang_cabang', $this->stockBranchIds($memberCabang))
            ->where('barang_status', '1')
            ->groupBy('barang_kode')
            ->selectRaw('barang_kode, SUM(CAST(barang_stock AS DECIMAL(12,2))) as total_stock')
            ->pluck('total_stock', 'barang_kode')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    protected function mapProductRow(object $row, int $tier, ?Collection $discountMap = null): object
    {
        $discountMap ??= $this->discounts->activeDiscountsByKode();
        $base = $this->pricing->unitPrice($row, $tier);
        $disc = $discountMap->get($row->barang_kode);
        $priced = $this->discounts->applyDiscount($base, $disc);

        $row->price = $priced['price'];
        $compareUmum = $this->pricing->comparePrice($row, $tier);
        if ($compareUmum !== null && (! $priced['has_discount'] || $compareUmum > $priced['price'])) {
            $row->price_original = $compareUmum;
            $row->has_discount = true;
            $row->discount_label = null;
        } else {
            $row->price_original = $priced['price_original'];
            $row->has_discount = $priced['has_discount'];
            $row->discount_label = $priced['discount_label'];
        }
        $row->price_label = $this->pricing->tierLabel($tier);
        $row->stock = (float) ($row->barang_stock ?? 0);
        $row->in_stock = $row->stock > 0;
        $row->image_url = $this->imageUrl($row->barang_gambar ?? null);

        return $row;
    }

    public function product(int $cabangId, int $barangId, int $tier, ?int $memberCabang = null): ?object
    {
        if (NumartSource::erp()) {
            return $barangId > 0 ? $this->erpProducts($cabangId, $tier, ['id' => (string) $barangId], $memberCabang, 1)->first() : null;
        }

        $row = DB::connection('numart')
            ->table('barang')
            ->where('barang_id', $barangId)
            ->where('barang_cabang', $cabangId)
            ->where('barang_status', '1')
            ->first();

        if (! $row) {
            return null;
        }

        $stocks = $this->stockTotalsByKode([(string) $row->barang_kode], $memberCabang);
        $row->barang_stock = $stocks[$row->barang_kode] ?? 0;

        return $this->mapProductRow($row, $tier);
    }

    public function productByKode(int $cabangId, string $kode, int $tier, ?int $memberCabang = null): ?object
    {
        if (NumartSource::erp()) {
            return $kode !== '' ? $this->erpProducts($cabangId, $tier, ['kode' => $kode], $memberCabang, 1)->first() : null;
        }

        $row = DB::connection('numart')
            ->table('barang')
            ->where('barang_kode', $kode)
            ->where('barang_cabang', $cabangId)
            ->where('barang_status', '1')
            ->first();

        if (! $row) {
            return null;
        }

        $stocks = $this->stockTotalsByKode([$kode], $memberCabang);
        $row->barang_stock = $stocks[$kode] ?? 0;

        return $this->mapProductRow($row, $tier);
    }

    public function hasStockForKode(string $kode, ?int $memberCabang = null): bool
    {
        if (NumartSource::erp()) {
            $cabang = $memberCabang ?? (int) config('marketplace.catalog_cabang_display', 0);

            return (bool) $this->productByKode($cabang, $kode, 0, $memberCabang)?->in_stock;
        }

        return DB::connection('numart')
            ->table('barang')
            ->where('barang_kode', $kode)
            ->whereIn('barang_cabang', $this->stockBranchIds($memberCabang))
            ->where('barang_status', '1')
            ->whereRaw('CAST(barang_stock AS DECIMAL(12,2)) > 0')
            ->exists();
    }

    /* ---------------- Jalur NUMART_SOURCE=erp (API NUMART ERP) ---------------- */

    /**
     * Terjemahkan filter katalog ke parameter /integrasi/katalog. null = hasil pasti kosong.
     * Batasan ERP: urut nama Z-A & harga tidak didukung (jatuh ke urut nama A-Z); filter stok "habis" tidak didukung (diabaikan);
     * urut terlaris di ERP hanya memuat barang yang terjual dalam N hari.
     *
     * @return array<string, mixed>|null
     */
    protected function erpParams(CatalogProductFilters $filters, ?string $search, ?int $kategoriId): ?array
    {
        $p = ['q' => $search ?: null, 'kategori_id' => $kategoriId ?: ($filters->kategoriId ?: null)];
        $p['urut'] = match ($filters->sort) {
            CatalogProductFilters::SORT_TERBARU => 'terbaru',
            CatalogProductFilters::SORT_TERLARIS => 'terlaris',
            default => 'nama',
        };
        if ($p['urut'] === 'terlaris') {
            $p['terlaris_hari'] = (int) config('marketplace.best_sellers_days', 7);
        }
        if ($filters->stok === 'ada') {
            $p['hanya_ada_stok'] = 1;
        }
        if ($filters->promo === 'diskon') {
            $kodes = $this->discounts->activeDiscountsByKode()->keys()->take(100)->all();
            if ($kodes === []) {
                return null;
            }
            $p['kode'] = implode(',', $kodes);
        }

        return $p;
    }

    protected function erpProducts(int $cabangId, int $tier, array $params, ?int $memberCabang, int $limit): Collection
    {
        $res = app(ErpCatalog::class)->search($cabangId, $tier, $params, $this->stockBranchIds($memberCabang), 1, max(1, $limit));
        $discountMap = $this->discounts->activeDiscountsByKode();

        return $res['rows']->map(fn ($row) => $this->mapProductRow($row, $tier, $discountMap))->values();
    }

    protected function erpPaginate(int $cabangId, int $tier, ?array $params, int $perPage, string $pageName, ?int $memberCabang, ?int $forcedOffset = null, ?int $excludeBarangId = null): LengthAwarePaginator
    {
        $currentPage = $forcedOffset !== null ? (int) floor($forcedOffset / max(1, $perPage)) + 1 : max(1, (int) Request::input($pageName, 1));
        $items = collect();
        $total = 0;

        if ($params !== null) {
            $res = app(ErpCatalog::class)->search($cabangId, $tier, $params, $this->stockBranchIds($memberCabang), $currentPage, $perPage);
            $discountMap = $this->discounts->activeDiscountsByKode();
            $total = $res['total'];
            $items = $res['rows']
                ->reject(fn ($row) => $excludeBarangId && (int) $row->barang_id === $excludeBarangId)
                ->map(fn ($row) => $this->mapProductRow($row, $tier, $discountMap))
                ->values();
        }

        $paginator = new LengthAwarePaginator($items, $total, $perPage, $currentPage, ['path' => Request::url(), 'pageName' => $pageName]);

        return $forcedOffset !== null ? $paginator : $paginator->appends(collect(Request::query())->except($pageName)->all());
    }

    protected function imageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http')) {
            return $path;
        }

        return rtrim(config('marketplace.numart_asset_url'), '/').'/'.ltrim($path, '/');
    }
}
