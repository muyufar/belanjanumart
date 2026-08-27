<?php

namespace App\Http\Controllers;

use App\Services\BranchWhatsAppService;
use App\Services\CartSessionService;
use App\Services\CheckoutService;
use App\Services\MemberContextService;
use App\Services\PricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        protected CartSessionService $cart,
        protected CheckoutService $checkout,
        protected PricingService $pricing,
        protected MemberContextService $memberContext,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        if ($this->cart->count() === 0) {
            return redirect()->route('shop.index')->with('error', 'Keranjang masih kosong.');
        }

        $user = $request->user();
        $tier = $this->pricing->tierForUser($user);
        $subtotal = $this->cart->subtotal();
        $minOrder = $this->memberContext->minOrderAmount($tier);
        $cabang = $this->memberContext->memberCabangId($user);

        return view('checkout.create', [
            'items' => $this->cart->all(),
            'subtotal' => $subtotal,
            'shipping' => (int) config('marketplace.default_shipping_fee', 0),
            'cartCount' => $this->cart->count(),
            'tierLabel' => $this->pricing->tierLabel($tier),
            'minOrder' => $minOrder,
            'canCod' => $this->memberContext->canUseCod($user),
            'branchLabel' => $this->memberContext->branchLabel($cabang),
            'user' => $user,
            'belowMin' => $subtotal < $minOrder,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'phone' => 'required|string|max:30',
            'address' => 'required|string|max:1000',
            'payment_method' => 'required|in:cod,transfer',
        ]);

        $cart = array_map(fn ($r) => [
            'barang_id' => $r['barang_id'],
            'barang_kode' => $r['barang_kode'],
            'qty' => $r['qty'],
        ], $this->cart->all());

        if ($cart === []) {
            return redirect()->route('shop.index');
        }

        try {
            $order = $this->checkout->placeOrder($validated, $cart, $request->user());
            $this->cart->clear();

            $message = $validated['payment_method'] === 'cod'
                ? 'Pesanan COD dibuat. Kirim detail pesanan via WhatsApp ke cabang.'
                : 'Pesanan transfer dibuat. Scan QRIS dan upload bukti pembayaran.';

            return redirect()->route('orders.show', $order)->with('success', $message);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }
}
