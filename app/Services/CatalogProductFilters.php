<?php

namespace App\Services;

use Illuminate\Http\Request;

class CatalogProductFilters
{
    public const SORT_NAMA_ASC = 'nama_asc';

    public const SORT_NAMA_DESC = 'nama_desc';

    public const SORT_HARGA_ASC = 'harga_asc';

    public const SORT_HARGA_DESC = 'harga_desc';

    public const SORT_TERBARU = 'terbaru';

    public const SORT_TERLARIS = 'terlaris';

    /** @var list<string> */
    public const SORTS = [
        self::SORT_NAMA_ASC,
        self::SORT_NAMA_DESC,
        self::SORT_HARGA_ASC,
        self::SORT_HARGA_DESC,
        self::SORT_TERBARU,
        self::SORT_TERLARIS,
    ];

    public function __construct(
        public string $sort = self::SORT_NAMA_ASC,
        public string $stok = 'all',
        public string $promo = 'all',
        public ?int $kategoriId = null,
    ) {}

    public static function fromRequest(Request $request, ?string $tipe = null, ?int $fixedKategoriId = null): self
    {
        $sort = $request->string('sort')->toString();
        if ($sort === '' || ! in_array($sort, self::SORTS, true)) {
            $sort = match ($tipe) {
                CatalogService::TIPE_TERBARU => self::SORT_TERBARU,
                CatalogService::TIPE_TERLARIS => self::SORT_TERLARIS,
                default => self::SORT_NAMA_ASC,
            };
        }

        $stok = $request->string('stok')->toString();
        $stok = in_array($stok, ['ada', 'habis'], true) ? $stok : 'all';

        $promo = $request->string('promo')->toString() === 'diskon' ? 'diskon' : 'all';

        if ($fixedKategoriId !== null) {
            $kategori = $fixedKategoriId;
        } elseif ($tipe) {
            $kategori = null;
        } elseif ($request->has('kategori')) {
            $kategori = $request->integer('kategori') ?: null;
        } else {
            $kategori = null;
        }

        return new self($sort, $stok, $promo, $kategori);
    }

    public function isActive(?string $tipe = null, bool $fixedKategori = false): bool
    {
        $defaultSort = match ($tipe) {
            CatalogService::TIPE_TERBARU => self::SORT_TERBARU,
            CatalogService::TIPE_TERLARIS => self::SORT_TERLARIS,
            default => self::SORT_NAMA_ASC,
        };

        return $this->sort !== $defaultSort
            || $this->stok !== 'all'
            || $this->promo !== 'all'
            || ($tipe === null && ! $fixedKategori && $this->kategoriId !== null);
    }

    /** @return array<string, mixed> */
    public function queryParams(?string $except = null): array
    {
        $params = [];

        if ($this->sort !== self::SORT_NAMA_ASC) {
            $params['sort'] = $this->sort;
        }
        if ($this->stok !== 'all') {
            $params['stok'] = $this->stok;
        }
        if ($this->promo !== 'all') {
            $params['promo'] = $this->promo;
        }
        if ($this->kategoriId) {
            $params['kategori'] = $this->kategoriId;
        }

        if ($except) {
            unset($params[$except]);
        }

        return $params;
    }
}
