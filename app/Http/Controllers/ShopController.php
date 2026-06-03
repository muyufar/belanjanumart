<?php

namespace App\Http\Controllers;

use App\Services\CartSessionService;
use App\Services\CatalogService;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function __construct(
        protected CatalogService $catalog,
        protected PricingService $pricing,
        protected CartSessionService $cart,
    ) {}

    public function index(Request $request): View
    {
        $cabang = (int) config('marketplace.catalog_cabang_display', 0);
        $tier = $this->pricing->tierForUser($request->user());
        $search = $request->string('q')->toString() ?: null;
        $tipe = $request->string('tipe')->toString();
        $tipe = in_array($tipe, CatalogService::SPECIAL_TIPES, true) ? $tipe : null;
        $kategoriId = $tipe ? null : ($request->integer('kategori') ?: null);
        $perPage = (int) config('marketplace.products_per_page', 20);

        $products = match ($tipe) {
            CatalogService::TIPE_TERBARU => $this->catalog->paginateLatestProducts($cabang, $tier, $search, $perPage),
            CatalogService::TIPE_TERLARIS => $this->catalog->paginateBestSellingProducts($cabang, $tier, $search, $perPage),
            default => $this->catalog->paginateProducts($cabang, $tier, $search, $kategoriId, $perPage),
        };

        $showHomeSections = ! $search && ! $kategoriId && ! $tipe && $products->currentPage() === 1;

        return view('shop.index', [
            'products' => $products,
            'categories' => $this->catalog->categories($cabang),
            'latestProducts' => $showHomeSections ? $this->catalog->latestProducts($cabang, $tier) : collect(),
            'bestSellers' => $showHomeSections ? $this->catalog->bestSellingProducts($cabang, $tier) : collect(),
            'discounted' => $showHomeSections ? $this->catalog->discountedProducts($cabang, $tier) : collect(),
            'showHomeSections' => $showHomeSections,
            'tier' => $tier,
            'tierLabel' => $this->pricing->tierLabel($tier),
            'cartCount' => $this->cart->count(),
            'search' => $search,
            'kategoriId' => $kategoriId,
            'tipe' => $tipe,
            'bestSellersDays' => (int) config('marketplace.best_sellers_days', 7),
        ]);
    }

    public function show(Request $request, int $barangId): View
    {
        $cabang = (int) config('marketplace.catalog_cabang_display', 0);
        $tier = $this->pricing->tierForUser($request->user());
        $product = $this->catalog->product($cabang, $barangId, $tier);

        abort_unless($product, 404);

        $kategoriId = (int) ($product->kategori_id ?? 0);
        $relatedProducts = null;

        if ($kategoriId > 0) {
            $relatedProducts = $this->catalog->paginateProducts(
                $cabang,
                $tier,
                null,
                $kategoriId,
                (int) config('marketplace.related_products_per_page', 12),
                'rel_page',
                (int) $product->barang_id
            );
        }

        return view('shop.show', [
            'product' => $product,
            'tierLabel' => $this->pricing->tierLabel($tier),
            'cartCount' => $this->cart->count(),
            'relatedProducts' => $relatedProducts,
            'kategoriId' => $kategoriId,
        ]);
    }
}
