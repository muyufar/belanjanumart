<?php

namespace App\Http\Controllers;

use App\Services\BranchWhatsAppService;
use App\Services\CartSessionService;
use App\Services\CheckoutService;
use App\Services\MemberContextService;
use App\Services\PricingService;
use App\Services\ShippingService;
use Illuminate\Support\Facades\DB;
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
        protected ShippingService $delivery,
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

        $shippingRequired = $this->delivery->required($cabang);
        $quotes = $shippingRequired ? DB::table('shipping_quotes')->where('user_id', $user->id)->where('branch_id', $cabang)->orderByDesc('id')->limit(10)->get() : collect();
        $quote = $quotes->firstWhere('id', (int) $request->query('quote'));
        $quoteData = $quote ? json_decode($quote->request_json, true) : null;
        $quoteSnapshot = $quote && $quote->snapshot_json ? json_decode($quote->snapshot_json, true) : null;
        $quoteReady = $quote && $quote->status === 'approved' && $quote->expires_at > gmdate('Y-m-d H:i:s');
        $items = $this->cart->all();
        if ($quoteReady) {
            try {
                $current = $this->delivery->context($items, $user, $quoteData + ['shipping_service' => $quoteData['service']]);
                $quoteReady = hash_equals($quote->fingerprint, $current['fingerprint']);
                if ($quoteReady) {
                    $subtotal = $quoteData['basket']['subtotal'];
                    $items = array_map(fn ($l) => ['barang_nama' => $l['name'], 'price' => $l['price'], 'qty' => $l['qty']], $quoteData['basket']['lines']);
                }
            } catch (\DomainException $e) {
                $quoteReady = false;
            }
        }

        return view('checkout.create', [
            'items' => $items,
            'subtotal' => $subtotal,
            'shipping' => $quoteReady ? $quoteSnapshot['money']['customer_fee'] : (int) config('marketplace.default_shipping_fee', 0),
            'shippingRequired' => $shippingRequired,
            'quotes' => $quotes, 'quote' => $quote, 'quoteData' => $quoteData, 'quoteSnapshot' => $quoteSnapshot, 'quoteReady' => $quoteReady,
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
            'shipping_quote_id' => 'nullable|integer|min:1',
            'shipping_service' => 'required_with:shipping_quote_id|nullable|in:direct,scheduled,manual',
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

    public function requestShipping(Request $request): RedirectResponse
    {
        $input = $request->validate(['name' => 'required|string|max:200', 'phone' => 'required|string|max:30',
            'address' => 'required|string|max:1000', 'payment_method' => 'required|in:cod,transfer',
            'shipping_service' => 'required|in:direct,scheduled,manual']);
        try {
            $context = $this->delivery->context($this->cart->all(), $request->user(), $input);
            $id = $this->delivery->engine()->request($request->user()->id, $this->memberContext->memberCabangId($request->user()), $context);
            return redirect()->route('checkout.create', ['quote' => $id])->with('success', 'Permintaan ongkir dikirim. Admin akan memeriksa rute, muatan, dan jadwal. Muat ulang halaman ini untuk melihat penawaran.');
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function cancelShipping(Request $request, int $quote): RedirectResponse
    {
        try {
            $this->delivery->engine()->cancelQuote($quote, 'customer:'.$request->user()->id, $request->user()->id);
            return redirect()->route('checkout.create')->with('success', 'Penawaran dibatalkan.');
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
