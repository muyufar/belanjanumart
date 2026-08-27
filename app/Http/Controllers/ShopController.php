<?php

namespace App\Http\Controllers;

use App\Services\CartSessionService;
use App\Services\CatalogService;
use App\Services\MemberContextService;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function __construct(
        protected CatalogService $catalog,
        protected PricingService $pricing,
        protected CartSessionService $cart,
        protected MemberContextService $memberContext,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $cabang = $this->memberContext->memberCabangId($user);
        $tier = $this->pricing->tierForUser($user);
        $search = $request->string('q')->toString() ?: null;
        $tipe = $request->string('tipe')->toString();
        $tipe = in_array($tipe, CatalogService::SPECIAL_TIPES, true) ? $tipe : null;
        $kategoriId = $tipe ? null : ($request->integer('kategori') ?: null);
        $perPage = (int) config('marketplace.products_per_page', 20);

        try {
            $products = match ($tipe) {
                CatalogService::TIPE_TERBARU => $this->catalog->paginateLatestProducts($cabang, $tier, $search, $perPage, 'page', $cabang),
                CatalogService::TIPE_TERLARIS => $this->catalog->paginateBestSellingProducts($cabang, $tier, $search, $perPage, 'page', $cabang),
                default => $this->catalog->paginateProducts($cabang, $tier, $search, $kategoriId, $perPage, 'page', null, null, $cabang),
            };

            $showHomeSections = ! $search && ! $kategoriId && ! $tipe && $products->currentPage() === 1;

            return view('shop.index', [
                'products' => $products,
                'categories' => $this->catalog->categoriesFromPusat(),
                'latestProducts' => $showHomeSections ? $this->catalog->latestProducts($cabang, $tier, null, $cabang) : collect(),
                'bestSellers' => $showHomeSections ? $this->catalog->bestSellingProducts($cabang, $tier, null, $cabang) : collect(),
                'discounted' => $showHomeSections ? $this->catalog->discountedProducts($cabang, $tier, null, $cabang) : collect(),
                'showHomeSections' => $showHomeSections,
                'tier' => $tier,
                'tierLabel' => $this->pricing->tierLabel($tier),
                'minOrder' => $this->memberContext->minOrderAmount($tier),
                'branchLabel' => $this->memberContext->branchLabel($cabang),
                'cartCount' => $this->cart->count(),
                'search' => $search,
                'kategoriId' => $kategoriId,
                'tipe' => $tipe,
                'bestSellersDays' => (int) config('marketplace.best_sellers_days', 7),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return view('shop.unavailable', [
                'cartCount' => $this->cart->count(),
            ]);
        }
    }

    public function show(Request $request, int $barangId): View
    {
        $user = $request->user();
        $cabang = $this->memberContext->memberCabangId($user);
        $tier = $this->pricing->tierForUser($user);
        $product = $this->catalog->product($cabang, $barangId, $tier, $cabang);

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
                (int) $product->barang_id,
                null,
                $cabang
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

    public function categories(Request $request): View
    {
        try {
            return view('shop.categories', [
                'categories' => $this->catalog->categoriesFromPusat(null),
                'cartCount' => $this->cart->count(),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return view('shop.unavailable', [
                'cartCount' => $this->cart->count(),
            ]);
        }
    }

    public function category(Request $request, int $kategoriId): View
    {
        $user = $request->user();
        $cabang = $this->memberContext->memberCabangId($user);
        $tier = $this->pricing->tierForUser($user);
        $search = $request->string('q')->toString() ?: null;
        $perPage = (int) config('marketplace.products_per_page', 20);

        try {
            $category = $this->catalog->categoryById($kategoriId);
            abort_unless($category, 404);

            $products = $this->catalog->paginateProducts(
                $cabang,
                $tier,
                $search,
                $kategoriId,
                $perPage,
                'page',
                null,
                null,
                $cabang
            );

            return view('shop.category', [
                'category' => $category,
                'products' => $products,
                'cartCount' => $this->cart->count(),
                'search' => $search,
                'tierLabel' => $this->pricing->tierLabel($tier),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return view('shop.unavailable', [
                'cartCount' => $this->cart->count(),
            ]);
        }
    }
}
