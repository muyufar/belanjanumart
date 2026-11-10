<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockHold;
use App\Services\Erp\ErpOrders;
use App\Support\NumartSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutService
{
    public function __construct(
        protected CatalogService $catalog,
        protected PricingService $pricing,
        protected MemberContextService $memberContext,
        protected ShippingService $delivery,
    ) {}

    /**
     * @param  array<int, array{barang_id: int, barang_kode: string, qty: int}>  $cart
     */
    public function placeOrder(array $input, array $cart, \App\Models\User $actingUser, ?\App\Models\User $buyerUser = null): Order
    {
        $buyer = $buyerUser ?? $actingUser;
        $placedById = ($buyerUser && $buyerUser->id !== $actingUser->id) ? $actingUser->id : null;

        $tier = $this->pricing->tierForUser($buyer);
        $cabang = $this->memberContext->memberCabangId($buyer);
        $branchLabel = $this->memberContext->branchLabel($cabang);
        $paymentMethod = (string) ($input['payment_method'] ?? 'transfer');

        if (! in_array($paymentMethod, ['cod', 'transfer'], true)) {
            throw new \InvalidArgumentException('Metode pembayaran tidak valid.');
        }

        if ($paymentMethod === 'cod' && ! $this->memberContext->canUseCod($buyer)) {
            throw new \InvalidArgumentException('COD hanya untuk member terverifikasi. Gunakan transfer atau lengkapi verifikasi akun.');
        }

        $lines = [];
        $subtotal = 0;
        $itemsPayload = [];

        foreach ($cart as $row) {
            $product = $this->catalog->productByKode($cabang, $row['barang_kode'], $tier, $cabang);

            if (! $product) {
                throw new \InvalidArgumentException('Produk tidak ditemukan: '.$row['barang_kode']);
            }

            $qty = max(1, (int) $row['qty']);
            $unit = (int) $product->price;
            if (NumartSource::erp() && $unit <= 0) {
                throw new \InvalidArgumentException('Harga belum diatur untuk '.$product->barang_nama);
            }
            $lineTotal = $unit * $qty;
            $subtotal += $lineTotal;

            $need = $qty * (int) ($product->satuan_isi_1 ?? 1);
            if ((float) $product->barang_stock < $need) {
                throw new \InvalidArgumentException('Stok tidak cukup untuk '.$product->barang_nama);
            }

            $itemsPayload[] = [
                'product' => $product,
                'qty' => $qty,
                'unit' => $unit,
                'line_total' => $lineTotal,
            ];
        }

        $minOrder = $this->memberContext->minOrderAmount($tier);
        if ($subtotal < $minOrder) {
            throw new \InvalidArgumentException(
                'Minimal pembelian '.$this->pricing->tierLabel($tier).' Rp '.number_format($minOrder, 0, ',', '.')
            );
        }

        $shipping = (int) config('marketplace.default_shipping_fee', 0);
        $quoteId = (int) ($input['shipping_quote_id'] ?? 0);
        $shippingContext = null;
        if ($this->delivery->required($cabang) || $quoteId > 0) {
            if ($quoteId < 1) throw new \DomainException('Ajukan ongkir dan tunggu verifikasi admin sebelum membuat pesanan.');
            $shippingContext = $this->delivery->context($cart, $buyer, $input);
            if ($shippingContext['basket']['subtotal'] !== $subtotal) throw new \DomainException('Harga berubah. Muat ulang checkout.');
            $costs = array_column($shippingContext['basket']['lines'], 'unit_cost', 'code');
            foreach ($itemsPayload as &$payload) $payload['unit_cost'] = $costs[$payload['product']->barang_kode];
            unset($payload);
        }
        $grand = $subtotal + $shipping;
        $holdMinutes = (int) config('marketplace.stock_hold_minutes', 15);
        $expires = now()->addMinutes($holdMinutes);
        $status = $paymentMethod === 'cod' ? 'pending_cod' : 'pending_transfer';
        [$customerLat, $customerLng] = $this->resolveCustomerCoordinates($input);

        $buyer->loadMissing('affiliateKader');
        $referralCode = $buyer->affiliate_kader_id ? $buyer->affiliateKader?->affiliate_code : null;

        return DB::transaction(function () use ($input, $buyer, $placedById, $referralCode, $tier, $cabang, $branchLabel, $subtotal, $shipping, $grand, $expires, $itemsPayload, $paymentMethod, $status, $quoteId, $shippingContext, $customerLat, $customerLng) {
            $snapshot = null;
            if ($shippingContext !== null) {
                $snapshot = $this->delivery->engine()->consume($quoteId, $buyer->id, $cabang, $shippingContext['fingerprint']);
                $shipping = $snapshot['money']['customer_fee'];
                $grand = $subtotal + $shipping;
            }
            $order = Order::create([
                ...($snapshot === null ? [] : ['shipping_quote_id' => $quoteId, 'shipping_snapshot' => $snapshot]),
                'order_number' => 'MP-'.strtoupper(Str::random(10)),
                'user_id' => $buyer->id,
                'placed_by_user_id' => $placedById,
                'affiliate_referral_code' => $referralCode,
                'price_tier' => $tier,
                'fulfillment_cabang' => $cabang,
                'fulfillment_label' => $branchLabel,
                'customer_lat' => $customerLat,
                'customer_lng' => $customerLng,
                'customer_name' => $input['name'],
                'customer_phone' => $input['phone'],
                'customer_address' => $input['address'],
                'subtotal' => $subtotal,
                'shipping_fee' => $shipping,
                'discount' => 0,
                'grand_total' => $grand,
                'payment_method' => $paymentMethod,
                'status' => $status,
                'tracking_status' => \App\Services\OrderTrackingService::AWAITING_PAYMENT,
                'tracking_updated_at' => now(),
                'expires_at' => $expires,
                'numart_source' => NumartSource::current(),
            ]);

            $erpLines = [];
            foreach ($itemsPayload as $payload) {
                $p = $payload['product'];
                OrderItem::create([
                    'order_id' => $order->id,
                    'barang_id' => (int) $p->barang_id,
                    'barang_kode' => $p->barang_kode,
                    'barang_nama' => $p->barang_nama,
                    'qty' => $payload['qty'],
                    'unit_price' => $payload['unit'],
                    'line_total' => $payload['line_total'],
                    'harga_beli' => $payload['unit_cost'] ?? (int) $p->barang_harga_beli,
                    'satuan_id' => (int) ($p->satuan_id ?? 0),
                    'konversi_isi' => (int) ($p->satuan_isi_1 ?? 1),
                ]);

                if (NumartSource::erp()) {
                    $erpLines[] = ['barang_id' => (int) $p->barang_id, 'level' => (int) ($p->erp_level ?? 1), 'qty' => (string) $payload['qty']];

                    continue;
                }

                StockHold::create([
                    'order_id' => $order->id,
                    'barang_id' => (int) $p->barang_id,
                    'cabang_id' => $cabang,
                    'qty_pcs' => $payload['qty'] * (int) ($p->satuan_isi_1 ?? 1),
                    'expires_at' => $expires,
                ]);
            }

            if ($erpLines !== []) {
                // Kunci stok di ERP (ganti StockHold lokal). Gagal → transaksi lokal dibatalkan, pesanan tidak dibuat.
                app(ErpOrders::class)->reserve($order, $erpLines);
            }

            if ($snapshot !== null) DB::table('shipping_quotes')->where('id', $quoteId)->update(['order_id' => $order->id]);
            return $order->fresh(['items', 'user']);
        });
    }

    /** Dipanggil webhook BRI saat VA lunas (sebelumnya metode ini belum ada → webhook BRI selalu error). */
    public function markPaid(Order $order): Order
    {
        [$status, $payload] = app(OrderPaymentService::class)->markPaid($order);
        if ($status >= 400) {
            throw new \RuntimeException($payload['message'] ?? 'Gagal menandai lunas.');
        }

        return $order->fresh(['items', 'user']);
    }

    public function submitPaymentProof(Order $order, string $path): Order
    {
        if ($order->payment_method !== 'transfer') {
            throw new \InvalidArgumentException('Bukti transfer hanya untuk pesanan transfer.');
        }

        if (! in_array($order->status, ['pending_transfer', 'proof_submitted'], true)) {
            throw new \InvalidArgumentException('Pesanan tidak dapat menerima bukti transfer.');
        }

        $order->update([
            'payment_proof_path' => $path,
            'payment_proof_at' => now(),
            'status' => 'proof_submitted',
        ]);

        return $order->fresh(['items', 'user']);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: float, 1: float}
     */
    protected function resolveCustomerCoordinates(array $input): array
    {
        $lat = isset($input['customer_lat']) ? (float) $input['customer_lat'] : null;
        $lng = isset($input['customer_lng']) ? (float) $input['customer_lng'] : null;

        if ($lat === null || $lng === null) {
            throw new \InvalidArgumentException('Lokasi GPS wajib diambil sebelum checkout.');
        }

        if (($input['gps_valid'] ?? '') !== '1') {
            throw new \InvalidArgumentException('Konfirmasi lokasi GPS belum valid.');
        }

        $accuracy = isset($input['gps_accuracy']) && $input['gps_accuracy'] !== ''
            ? (float) $input['gps_accuracy']
            : null;

        if ($accuracy !== null && $accuracy > 500) {
            throw new \InvalidArgumentException('Akurasi GPS terlalu rendah. Ambil ulang lokasi di area terbuka.');
        }

        return [$lat, $lng];
    }
}
