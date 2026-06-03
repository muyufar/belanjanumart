<?php

namespace App\Http\Controllers;

use App\Services\CartSessionService;
use App\Services\CheckoutService;
use App\Services\FulfillmentService;
use App\Services\PricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        protected CartSessionService $cart,
        protected CheckoutService $checkout,
        protected FulfillmentService $fulfillment,
        protected PricingService $pricing,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        if ($this->cart->count() === 0) {
            return redirect()->route('shop.index')->with('error', 'Keranjang masih kosong.');
        }

        $preview = $this->previewFulfillment($request);

        return view('checkout.create', [
            'items' => $this->cart->all(),
            'subtotal' => $this->cart->subtotal(),
            'shipping' => (int) config('marketplace.default_shipping_fee', 10000),
            'cartCount' => $this->cart->count(),
            'tierLabel' => $this->pricing->tierLabel($this->pricing->tierForUser($request->user())),
            'preview' => $preview,
            'user' => $request->user(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'phone' => 'required|string|max:30',
            'address' => 'required|string|max:1000',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
        ]);

        $cart = array_map(fn ($r) => [
            'barang_id' => $r['barang_id'],
            'barang_kode' => $r['barang_kode'],
            'qty' => $r['qty'],
        ], $this->cart->all());

        if ($cart === []) {
            return redirect()->route('shop.index');
        }

        $validated['preview_cabang'] = (int) config('marketplace.catalog_cabang_display', 0);

        if ($request->hasSession()) {
            $request->session()->put('checkout_lat', $validated['lat'] ?? null);
            $request->session()->put('checkout_lng', $validated['lng'] ?? null);
        }

        try {
            $order = $this->checkout->placeOrder($validated, $cart, $request->user());
            $this->cart->clear();

            return redirect()->route('orders.show', $order)->with('success', 'Pesanan dibuat. Silakan bayar via Virtual Account BRI.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    protected function previewFulfillment(Request $request): array
    {
        $lines = array_map(fn ($r) => [
            'barang_kode' => $r['barang_kode'],
            'qty' => $r['qty'],
            'konversi_isi' => 1,
        ], $this->cart->all());

        $lat = $request->hasSession() ? $request->session()->get('checkout_lat') : null;
        $lng = $request->hasSession() ? $request->session()->get('checkout_lng') : null;

        return $this->fulfillment->resolve(
            $lat !== null && $lat !== '' ? (float) $lat : null,
            $lng !== null && $lng !== '' ? (float) $lng : null,
            $lines
        );
    }
}
