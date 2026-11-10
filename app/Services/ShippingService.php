<?php

namespace App\Services;

use App\Models\User;
use App\Services\Erp\ErpShipping;
use App\Support\NumartSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Nugrosir\Shipping;

class ShippingService
{
    public function __construct(protected CatalogService $catalog, protected PricingService $pricing, protected MemberContextService $members) {}

    public function engine(): Shipping
    {
        require_once app_path('Support/NugrosirShipping.php');
        return new Shipping(DB::connection()->getPdo());
    }

    public function required(int $branch): bool
    {
        if (NumartSource::erp()) return app(ErpShipping::class)->required($branch);
        if (!Schema::hasTable('shipping_campaigns')) return false;
        $c = $this->engine()->campaign();
        // Once activated, pausing must not silently restore the old free checkout.
        return (int) $c['branch_id'] === $branch && $c['ends_at'] !== null;
    }

    /** Permintaan ongkir pelanggan di cabang ini (bentuk baris shipping_quotes). */
    public function quotes(User $user, int $branch): \Illuminate\Support\Collection
    {
        if (NumartSource::erp()) return app(ErpShipping::class)->quotes($user->id, $branch);
        return DB::table('shipping_quotes')->where('user_id', $user->id)->where('branch_id', $branch)->orderByDesc('id')->limit(10)->get();
    }

    public function request(User $user, int $branch, array $context): int
    {
        return NumartSource::erp() ? app(ErpShipping::class)->request($user->id, $branch, $context) : $this->engine()->request($user->id, $branch, $context);
    }

    public function cancel(int $quote, User $user): void
    {
        NumartSource::erp() ? app(ErpShipping::class)->cancel($quote, $user->id) : $this->engine()->cancelQuote($quote, 'customer:'.$user->id, $user->id);
    }

    /** Pakai penawaran saat membuat pesanan (di dalam transaksi pesanan). @return array snapshot untuk orders.shipping_snapshot */
    public function consume(int $quote, User $user, int $branch, string $fingerprint, string $orderNumber): array
    {
        return NumartSource::erp() ? app(ErpShipping::class)->consume($quote, $user->id, $fingerprint, $orderNumber)
            : $this->engine()->consume($quote, $user->id, $branch, $fingerprint);
    }

    /** Pesanan gagal dibuat setelah penawaran dipakai: di ERP penawaran dilepas lagi (jalur pos: rollback DB lokal sudah cukup). */
    public function release(int $quote, string $orderNumber): void
    {
        if (NumartSource::erp()) app(ErpShipping::class)->release($quote, $orderNumber);
    }

    public function context(array $cart, User $user, array $input): array
    {
        $branch = $this->members->memberCabangId($user);
        $tier = $this->pricing->tierForUser($user);
        $lines = [];
        $subtotal = $cost = 0;
        $known = true;
        // O-5 (keputusan pemilik 2026-11-11): subsidi ongkir dihitung dari jarak, jumlah pesanan & margin toko. Pada NUMART_SOURCE=erp
        // margin dihitung ERP dari HPP-nya sendiri saat menerbitkan penawaran (HPP tidak dibuka ke web ini) → di sini cost tetap 0 /
        // cost_known false; nilai itu tidak dipakai untuk subsidi di jalur erp.
        $erp = NumartSource::erp();
        $hasAverage = ! $erp && Schema::connection('numart')->hasColumn('barang', 'barang_harga_beli_rata');
        foreach ($cart as $row) {
            $p = $this->catalog->productByKode($branch, $row['barang_kode'], $tier, $branch);
            if (!$p) throw new \DomainException('Produk tidak tersedia. Perbarui keranjang.');
            $qty = (int) $row['qty'];
            $conversion = max(1, (int) $p->satuan_isi_1);
            if ($qty < 1 || $qty * $conversion > (float) $p->barang_stock) throw new \DomainException('Stok tidak cukup: '.$p->barang_nama);
            $hpp = (float) $p->barang_harga_beli;
            if ($hasAverage) {
                $avg = DB::connection('numart')->table('barang')->where('barang_id', $p->barang_id)->value('barang_harga_beli_rata');
                if ((float) $avg > 0) $hpp = (float) $avg;
            }
            $known = $known && $hpp > 0;
            $unitCost = (int) ceil($hpp * $conversion);
            $lines[] = ['code' => (string) $p->barang_kode, 'name' => $p->barang_nama,
                'qty' => $qty, 'conversion' => $conversion, 'price' => (int) $p->price, 'unit_cost' => $unitCost];
            $subtotal += $qty * (int) $p->price;
            $cost += $qty * $unitCost;
        }
        if (!$lines || $subtotal < $this->members->minOrderAmount($tier)) throw new \DomainException('Keranjang belum memenuhi minimal belanja.');
        usort($lines, fn ($a, $b) => strcmp($a['code'], $b['code']));
        if ($input['payment_method'] === 'cod' && !$this->members->canUseCod($user)) throw new \DomainException('COD hanya untuk member terverifikasi.');
        $data = [
            'name' => trim($input['name']),
            'phone' => trim($input['phone']),
            'address' => trim($input['address']),
            'payment_method' => $input['payment_method'],
            'service' => $input['shipping_service'],
            'customer_lat' => (float) $input['customer_lat'],
            'customer_lng' => (float) $input['customer_lng'],
            'gps_accuracy' => isset($input['gps_accuracy']) ? (float) $input['gps_accuracy'] : null,
            'basket' => ['lines' => $lines, 'subtotal' => $subtotal, 'cost' => $cost, 'cost_known' => $known],
        ];
        $data['fingerprint'] = hash('sha256', json_encode([$branch, $tier, $data], JSON_THROW_ON_ERROR));
        return $data;
    }
}
