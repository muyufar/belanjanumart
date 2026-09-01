<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockHold;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutService
{
    public function __construct(
        protected CatalogService $catalog,
        protected PricingService $pricing,
        protected MemberContextService $memberContext,
    ) {}

    /**
     * @param  array<int, array{barang_id: int, barang_kode: string, qty: int}>  $cart
     */
    public function placeOrder(array $input, array $cart, \App\Models\User $user): Order
    {
        $tier = $this->pricing->tierForUser($user);
        $cabang = $this->memberContext->memberCabangId($user);
        $branchLabel = $this->memberContext->branchLabel($cabang);
        $paymentMethod = (string) ($input['payment_method'] ?? 'transfer');

        if (! in_array($paymentMethod, ['cod', 'transfer'], true)) {
            throw new \InvalidArgumentException('Metode pembayaran tidak valid.');
        }

        if ($paymentMethod === 'cod' && ! $this->memberContext->canUseCod($user)) {
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
        $grand = $subtotal + $shipping;
        $holdMinutes = (int) config('marketplace.stock_hold_minutes', 15);
        $expires = now()->addMinutes($holdMinutes);
        $status = $paymentMethod === 'cod' ? 'pending_cod' : 'pending_transfer';

        return DB::transaction(function () use ($input, $user, $tier, $cabang, $branchLabel, $subtotal, $shipping, $grand, $expires, $itemsPayload, $paymentMethod, $status) {
            $order = Order::create([
                'order_number' => 'MP-'.strtoupper(Str::random(10)),
                'user_id' => $user->id,
                'price_tier' => $tier,
                'fulfillment_cabang' => $cabang,
                'fulfillment_label' => $branchLabel,
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
            ]);

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

            return $order->fresh(['items', 'user']);
        });
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
}
