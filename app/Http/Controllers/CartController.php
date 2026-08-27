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

        $qty = max(1, $request->integer('qty', 1));
        $error = $this->stockErrorForQty($product, $qty);

        if ($error) {
            return back()->with('error', $error);
        }

        $this->cart->add($product, $qty);

        return back()->with('success', 'Produk ditambahkan ke keranjang.');
    }

    public function update(Request $request, int $barangId): RedirectResponse
    {
        $qty = $request->integer('qty');

        if ($qty < 1) {
            $this->cart->remove($barangId);

            return redirect()->route('cart.index');
        }

        $user = $request->user();
        $cabang = $this->memberContext->memberCabangId($user);
        $tier = $this->pricing->tierForUser($user);
        $product = $this->catalog->product($cabang, $barangId, $tier, $cabang);

        if (! $product) {
            $this->cart->remove($barangId);

            return redirect()->route('cart.index')->with('error', 'Produk tidak tersedia.');
        }

        $error = $this->stockErrorForQty($product, $qty, false);

        if ($error) {
            return redirect()->route('cart.index')->with('error', $error);
        }

        $this->cart->update($barangId, $qty);

        return redirect()->route('cart.index');
    }

    protected function stockErrorForQty(object $product, int $qty, bool $includeCart = true): ?string
    {
        $stock = (float) ($product->stock ?? 0);

        if ($stock <= 0) {
            return 'Stok produk habis.';
        }

        $maxQty = min(99, (int) floor($stock));
        $requested = $includeCart
            ? $qty + $this->cartQtyFor((int) $product->barang_id)
            : $qty;

        if ($requested > $maxQty) {
            if ($includeCart) {
                $available = max(0, $maxQty - $this->cartQtyFor((int) $product->barang_id));

                return $available > 0
                    ? 'Stok tidak cukup. Maksimal bisa ditambah '.$available.' pcs.'
                    : 'Stok tidak cukup untuk jumlah ini.';
            }

            return 'Stok tidak cukup. Tersedia: '.number_format($maxQty, 0, ',', '.').' pcs.';
        }

        return null;
    }

    protected function cartQtyFor(int $barangId): int
    {
        foreach ($this->cart->all() as $row) {
            if ($row['barang_id'] === $barangId) {
                return (int) $row['qty'];
            }
        }

        return 0;
    }

    public function destroy(int $barangId): RedirectResponse
    {
        $this->cart->remove($barangId);

        return redirect()->route('cart.index');
    }
}
