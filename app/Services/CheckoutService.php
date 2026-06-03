<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockHold;
use App\Services\Bri\BriPaymentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutService
{
    public function __construct(
        protected CatalogService $catalog,
        protected FulfillmentService $fulfillment,
        protected PricingService $pricing,
        protected BriPaymentService $bri,
    ) {}

    /**
     * @param  array<int, array{barang_id: int, barang_kode: string, qty: int}>  $cart
     */
    public function placeOrder(array $input, array $cart, ?\App\Models\User $user): Order
    {
        $tier = $this->pricing->tierForUser($user);
        $lines = [];
        $subtotal = 0;
        $itemsPayload = [];

        foreach ($cart as $row) {
            $product = $this->catalog->productByKode(
                (int) ($input['preview_cabang'] ?? 0),
                $row['barang_kode'],
                $tier
            );

            if (! $product) {
                throw new \InvalidArgumentException('Produk tidak ditemukan: '.$row['barang_kode']);
            }

            $qty = max(1, (int) $row['qty']);
            $unit = (int) $product->price;
            $lineTotal = $unit * $qty;
            $subtotal += $lineTotal;

            $lines[] = [
                'barang_kode' => $row['barang_kode'],
                'qty' => $qty,
                'konversi_isi' => (int) ($product->satuan_isi_1 ?? 1),
            ];

            $itemsPayload[] = [
                'product' => $product,
                'qty' => $qty,
                'unit' => $unit,
                'line_total' => $lineTotal,
            ];
        }

        $fulfillment = $this->fulfillment->resolve(
            isset($input['lat']) ? (float) $input['lat'] : null,
            isset($input['lng']) ? (float) $input['lng'] : null,
            $lines
        );

        $cabang = (int) $fulfillment['cabang_id'];

        foreach ($itemsPayload as $idx => $payload) {
            $product = $this->catalog->productByKode($cabang, $lines[$idx]['barang_kode'], $tier);
            if (! $product) {
                throw new \InvalidArgumentException('Produk tidak tersedia di '.$fulfillment['label']);
            }
            $need = $payload['qty'] * (int) ($product->satuan_isi_1 ?? 1);
            if ((float) $product->barang_stock < $need) {
                throw new \InvalidArgumentException('Stok tidak cukup untuk '.$product->barang_nama);
            }
            $itemsPayload[$idx]['product'] = $product;
        }

        $shipping = (int) config('marketplace.default_shipping_fee', 10000);
        $grand = $subtotal + $shipping;
        $holdMinutes = (int) config('marketplace.stock_hold_minutes', 15);
        $expires = now()->addMinutes($holdMinutes);

        return DB::transaction(function () use ($input, $user, $tier, $fulfillment, $cabang, $subtotal, $shipping, $grand, $expires, $itemsPayload, $lines) {
            $order = Order::create([
                'order_number' => 'MP-'.strtoupper(Str::random(10)),
                'user_id' => $user?->id,
                'price_tier' => $tier,
                'fulfillment_cabang' => $cabang,
                'fulfillment_label' => $fulfillment['label'],
                'customer_lat' => $input['lat'] ?? null,
                'customer_lng' => $input['lng'] ?? null,
                'customer_name' => $input['name'],
                'customer_phone' => $input['phone'],
                'customer_address' => $input['address'],
                'subtotal' => $subtotal,
                'shipping_fee' => $shipping,
                'discount' => 0,
                'grand_total' => $grand,
                'status' => 'pending_payment',
                'expires_at' => $expires,
            ]);

            foreach ($itemsPayload as $idx => $payload) {
                $p = $payload['product'];
                OrderItem::create([
                    'order_id' => $order->id,
                    'barang_id' => (int) $p->barang_id,
                    'barang_kode' => $p->barang_kode,
                    'barang_nama' => $p->barang_nama,
                    'qty' => $payload['qty'],
                    'unit_price' => $payload['unit'],
                    'line_total' => $payload['line_total'],
                    'harga_beli' => (int) $p->barang_harga_beli,
                    'satuan_id' => (int) ($p->satuan_id ?? 0),
                    'konversi_isi' => (int) ($p->satuan_isi_1 ?? 1),
                ]);

                StockHold::create([
                    'order_id' => $order->id,
                    'barang_id' => (int) $p->barang_id,
                    'cabang_id' => $cabang,
                    'qty_pcs' => $payload['qty'] * (int) ($p->satuan_isi_1 ?? 1),
                    'expires_at' => $expires,
                ]);
            }

            $this->bri->createVirtualAccount($order, $input['name']);

            return $order->fresh(['items', 'payment']);
        });
    }

    public function markPaid(Order $order): Order
    {
        if ($order->isPaid()) {
            return $order;
        }

        $order->payment?->update([
            'status' => 'paid',
            'paid_at' => Carbon::now(),
        ]);

        $order->update([
            'status' => 'paid',
            'paid_at' => Carbon::now(),
        ]);

        app(NumartOrderSyncService::class)->syncPaidOrder($order);

        return $order->fresh(['items', 'payment']);
    }
}
