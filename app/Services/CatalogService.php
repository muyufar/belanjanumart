<?php

namespace App\Services;

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
    public function stockBranchIds(): array
    {
        $ids = config('marketplace.stock_branch_ids');
        if (is_array($ids) && $ids !== []) {
            return array_map('intval', $ids);
        }

        return array_merge(
            [(int) config('marketplace.catalog_cabang_display', 0)],
            array_map('intval', config('marketplace.nearby_branch_ids', [1, 5]))
        );
    }

    public function categories(int $cabangId = 0): Collection
    {
        return DB::connection('numart')
            ->table('kategori')
            ->where('kategori_status', '1')
            ->where('kategori_cabang', $cabangId)
            ->orderBy('kategori_nama')
            ->limit(50)
            ->get();
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
    ): LengthAwarePaginator {
        $perPage = $perPage ?? (int) config('marketplace.products_per_page', 20);
        $query = $this->productsQuery($cabangId, $search, $kategoriId, $excludeBarangId)
            ->orderBy('b.barang_nama');

        if ($forcedOffset !== null) {
            $total = (clone $query)->count();
            $items = $this->fetchAndMapRows($query, $tier, $forcedOffset, $perPage);
            $currentPage = (int) floor($forcedOffset / $perPage) + 1;

            return new LengthAwarePaginator(
                $items,
                $total,
                $perPage,
                $currentPage,
                ['path' => Request::url(), 'pageName' => $pageName]
            );
        }

        return $this->paginateFromQuery($query, $tier, $perPage, $pageName);
    }

    /**
     * @param  list<string>  $kodes
     */
    public function productsByKodes(int $cabangId, int $tier, array $kodes, int $limit = 12): Collection
    {
        $kodes = array_values(array_filter(array_unique($kodes)));
        if ($kodes === []) {
            return collect();
        }

        $query = $this->productsQuery($cabangId, null, null, null)
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

    public function bestSellingProducts(int $cabangId, int $tier, ?int $limit = null): Collection
    {
        $limit = $limit ?? (int) config('marketplace.home_best_sellers_limit', 8);
        $topKodes = $this->topSellingKodes($limit * 3);

        return $this->productsByKodes($cabangId, $tier, $topKodes, $limit);
    }

    public function latestProducts(int $cabangId, int $tier, ?int $limit = null): Collection
    {
        $limit = $limit ?? (int) config('marketplace.home_latest_limit', 8);
        $query = $this->productsQuery($cabangId, null, null, null)
            ->orderByDesc('b.barang_tanggal')
            ->orderByDesc('b.barang_id');

        $rows = $query->limit($limit)->get();
        $discountMap = $this->discounts->activeDiscountsByKode();
        $kodes = $rows->pluck('barang_kode')->all();
        $stockTotals = $this->stockTotalsByKode($kodes);

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
    ): LengthAwarePaginator {
        $query = $this->productsQuery($cabangId, $search, null, null)
            ->orderByDesc('b.barang_tanggal')
            ->orderByDesc('b.barang_id');

        return $this->paginateFromQuery($query, $tier, $perPage, $pageName);
    }

    public function paginateBestSellingProducts(
        int $cabangId,
        int $tier,
        ?string $search = null,
        ?int $perPage = null,
        string $pageName = 'page',
    ): LengthAwarePaginator {
        $salesSub = $this->salesByKodeSubquery();
        $query = $this->productsQuery($cabangId, $search, null, null)
            ->joinSub($salesSub, 'bs', function ($join) {
                $join->on('bs.barang_kode', '=', 'b.barang_kode');
            })
            ->orderByDesc('bs.sold_qty');

        return $this->paginateFromQuery($query, $tier, $perPage, $pageName);
    }

    /**
     * @return list<string>
     */
    protected function topSellingKodes(int $limit): array
    {
        return $this->salesByKodeSubquery()
            ->orderByDesc('sold_qty')
            ->limit($limit)
            ->pluck('barang_kode')
            ->all();
    }

    protected function salesByKodeSubquery()
    {
        $days = (int) config('marketplace.best_sellers_days', 7);
        $since = now('Asia/Jakarta')->subDays($days)->toDateString();
        $branchIds = $this->stockBranchIds();

        return DB::connection('numart')
            ->table('penjualan as pj')
            ->join('barang as bx', 'bx.barang_id', '=', 'pj.penjualan_barang_id')
            ->where('pj.penjualan_date', '>=', $since)
            ->whereIn('pj.penjualan_cabang', $branchIds)
            ->groupBy('bx.barang_kode')
            ->selectRaw('bx.barang_kode as barang_kode, SUM(pj.barang_qty) as sold_qty');
    }

    protected function paginateFromQuery($query, int $tier, ?int $perPage, string $pageName): LengthAwarePaginator
    {
        $perPage = $perPage ?? (int) config('marketplace.products_per_page', 20);
        $total = (clone $query)->count();
        $currentPage = max(1, (int) Request::input($pageName, 1));
        $offset = ($currentPage - 1) * $perPage;
        $items = $this->fetchAndMapRows($query, $tier, $offset, $perPage);

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

    public function discountedProducts(int $cabangId, int $tier, ?int $limit = null): Collection
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

        return $this->productsByKodes($cabangId, $tier, $kodes, $limit)
            ->filter(fn ($p) => $p->has_discount ?? false)
            ->values();
    }

    protected function productsQuery(int $cabangId, ?string $search, ?int $kategoriId, ?int $excludeBarangId = null)
    {
        $branchIds = $this->stockBranchIds();

        $q = DB::connection('numart')
            ->table('barang as b')
            ->leftJoin('kategori as k', 'k.kategori_id', '=', 'b.kategori_id')
            ->where('b.barang_cabang', $cabangId)
            ->where('b.barang_status', '1')
            ->whereExists(function ($sub) use ($branchIds) {
                $sub->select(DB::raw(1))
                    ->from('barang as bx')
                    ->whereColumn('bx.barang_kode', 'b.barang_kode')
                    ->whereIn('bx.barang_cabang', $branchIds)
                    ->where('bx.barang_status', '1')
                    ->whereRaw('CAST(bx.barang_stock AS DECIMAL(12,2)) > 0');
            })
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

    protected function fetchAndMapRows($query, int $tier, int $offset, int $limit): Collection
    {
        $rows = (clone $query)
            ->offset($offset)
            ->limit($limit)
            ->get();

        $discountMap = $this->discounts->activeDiscountsByKode();
        $kodes = $rows->pluck('barang_kode')->all();
        $stockTotals = $this->stockTotalsByKode($kodes);

        return $rows->map(function ($row) use ($tier, $discountMap, $stockTotals) {
            $row->barang_stock = $stockTotals[$row->barang_kode] ?? (float) $row->barang_stock;

            return $this->mapProductRow($row, $tier, $discountMap);
        });
    }

    /**
     * @param  list<string>  $kodes
     * @return array<string, float>
     */
    protected function stockTotalsByKode(array $kodes): array
    {
        if ($kodes === []) {
            return [];
        }

        return DB::connection('numart')
            ->table('barang')
            ->whereIn('barang_kode', $kodes)
            ->whereIn('barang_cabang', $this->stockBranchIds())
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
        $row->price_original = $priced['price_original'];
        $row->has_discount = $priced['has_discount'];
        $row->discount_label = $priced['discount_label'];
        $row->price_label = $this->pricing->tierLabel($tier);
        $row->stock = (float) ($row->barang_stock ?? 0);
        $row->image_url = $this->imageUrl($row->barang_gambar ?? null);

        return $row;
    }

    public function product(int $cabangId, int $barangId, int $tier): ?object
    {
        $row = DB::connection('numart')
            ->table('barang')
            ->where('barang_id', $barangId)
            ->where('barang_cabang', $cabangId)
            ->where('barang_status', '1')
            ->first();

        if (! $row) {
            return null;
        }

        if (! $this->hasStockForKode((string) $row->barang_kode)) {
            return null;
        }

        $stocks = $this->stockTotalsByKode([(string) $row->barang_kode]);
        $row->barang_stock = $stocks[$row->barang_kode] ?? 0;

        return $this->mapProductRow($row, $tier);
    }

    public function productByKode(int $cabangId, string $kode, int $tier): ?object
    {
        if (! $this->hasStockForKode($kode)) {
            return null;
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

        $stocks = $this->stockTotalsByKode([$kode]);
        $row->barang_stock = $stocks[$kode] ?? 0;

        return $this->mapProductRow($row, $tier);
    }

    public function hasStockForKode(string $kode): bool
    {
        return DB::connection('numart')
            ->table('barang')
            ->where('barang_kode', $kode)
            ->whereIn('barang_cabang', $this->stockBranchIds())
            ->where('barang_status', '1')
            ->whereRaw('CAST(barang_stock AS DECIMAL(12,2)) > 0')
            ->exists();
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
