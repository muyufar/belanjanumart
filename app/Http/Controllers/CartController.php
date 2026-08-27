<?php

namespace App\Http\Controllers;

use App\Services\CartSessionService;
use App\Services\CatalogService;
use App\Services\MemberContextService;
use App\Services\PricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(
        protected CartSessionService $cart,
        protected CatalogService $catalog,
        protected PricingService $pricing,
        protected MemberContextService $memberContext,
    ) {}

    public function index(Request $request): View
    {
        $tier = $this->pricing->tierForUser($request->user());

        return view('cart.index', [
            'items' => $this->cart->all(),
            'subtotal' => $this->cart->subtotal(),
            'cartCount' => $this->cart->count(),
            'tierLabel' => $this->pricing->tierLabel($tier),
            'minOrder' => $this->memberContext->minOrderAmount($tier),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $cabang = $this->memberContext->memberCabangId($user);
        $tier = $this->pricing->tierForUser($user);
        $product = $this->catalog->product($cabang, $request->integer('barang_id'), $tier, $cabang);

        abort_unless($product, 404);

        $this->cart->add($product, max(1, $request->integer('qty', 1)));

        return back()->with('success', 'Produk ditambahkan ke keranjang.');
    }

    public function update(Request $request, int $barangId): RedirectResponse
    {
        $this->cart->update($barangId, $request->integer('qty'));

        return redirect()->route('cart.index');
    }

    public function destroy(int $barangId): RedirectResponse
    {
        $this->cart->remove($barangId);

        return redirect()->route('cart.index');
    }
}
