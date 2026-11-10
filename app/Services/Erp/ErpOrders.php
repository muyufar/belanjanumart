<?php

namespace App\Services\Erp;

use App\Models\Order;
use App\Services\NumartErpClient;
use App\Services\PricingService;
use Illuminate\Support\Facades\Log;

/**
 * Siklus pesanan di ERP: reservasi (checkout) → penuhi (lunas → nota + stok + jurnal + pengiriman) | lepas (batal).
 * Nomor pesanan belanjanumart (orders.order_number) = kunci idempotensi ERP; semua panggilan aman diulang.
 */
class ErpOrders
{
    public function __construct(
        private NumartErpClient $erp,
        private ErpBranchMap $branches,
        private ErpCatalog $catalog,
        private PricingService $pricing,
    ) {}

    /** @param list<array{barang_id:int, level:int, qty:string}> $lines barang_id = id barang ERP */
    public function reserve(Order $order, array $lines): array
    {
        try {
            return $this->erp->reserve($order->order_number, $this->branches->toErp((int) $order->fulfillment_cabang), $lines,
                (int) config('services.numart_erp.reserve_minutes', 120));
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'STOCK_INSUFFICIENT')) {
                throw new \InvalidArgumentException('Stok tidak cukup — barang sedang habis atau dipesan pembeli lain. Muat ulang keranjang.', 0, $e);
            }
            throw $e;
        }
    }

    public function release(Order $order): int
    {
        return (int) ($this->erp->release($order->order_number)['dilepas'] ?? 0);
    }

    /**
     * Pesanan lunas → nota ERP. Pesanan peralihan (dibuat saat NUMART_SOURCE=pos, belum punya reservasi ERP)
     * direservasi dulu berdasarkan kode barang.
     *
     * @return array data nota ERP (nota, grand_total, baris, …)
     */
    public function fulfill(Order $order): array
    {
        $order->loadMissing(['items', 'user', 'payment']);
        $cabang = (int) $order->fulfillment_cabang;
        $tier = (int) $order->price_tier;
        $rows = $this->catalog->search($cabang, $tier, ['kode' => $order->items->pluck('barang_kode')->unique()->implode(',')], [$cabang], 1, 100)['rows']
            ->keyBy('barang_kode');

        if ($order->numart_source !== 'erp') {
            $this->reserve($order, $order->items->map(function ($item) use ($rows) {
                $row = $rows[$item->barang_kode] ?? throw new \RuntimeException("Barang {$item->barang_kode} tidak ada di katalog ERP cabang ini.");

                return ['barang_id' => (int) $row->barang_id, 'level' => (int) $row->erp_level, 'qty' => (string) $item->qty];
            })->values()->all());
            $order->update(['numart_source' => 'erp']);
        }

        // Harga ERP sekarang (sebelum diskon online) − yang dibayar pembeli = diskon yang dikirim; total_mitra menjaga
        // agar nota = uang yang diterima. Harga ERP berubah setelah checkout → 409 PRICE_MISMATCH (perlu ditangani admin).
        $erpSubtotal = 0;
        foreach ($order->items as $item) {
            $row = $rows[$item->barang_kode] ?? null;
            $erpSubtotal += $row ? $this->pricing->unitPrice($row, $tier) * (int) $item->qty : 0;
        }
        $discount = max(0, $erpSubtotal - (int) $order->subtotal) + (int) $order->discount;

        return $this->erp->fulfill($order->order_number, array_filter([
            'tipe_harga' => ErpCatalog::tierName($tier),
            'metode_bayar' => $order->payment_method === 'cod' ? 'tunai' : 'nontunai',
            'pelanggan_id' => $this->customerFor($order, $tier),
            'ref_pembayaran' => $order->payment?->cust_code ?: $order->order_number,
            'ongkir' => (string) (int) $order->shipping_fee,
            'diskon' => (string) $discount,
            'total_mitra' => (string) (int) $order->grand_total,
            'pengiriman' => array_filter([
                'penerima' => $order->customer_name,
                'hp' => $order->customer_phone,
                'alamat' => $order->customer_address,
                'catatan' => 'Pesanan online '.$order->order_number,
            ]),
        ], fn ($v) => $v !== null));
    }

    /** Status kirim O-1: GET /pesanan/{nomor}/pengiriman — null bila belum ada (belum dipenuhi). */
    public function delivery(Order $order): ?array
    {
        try {
            return $this->erp->delivery($order->order_number);
        } catch (\RuntimeException $e) {
            if ($e->getCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * pelanggan_id ERP hanya dikirim bila tipe harganya sama dengan tipe harga pesanan (ERP menolak bila beda).
     * Beda tipe terjadi bila tier diubah lokal (mis. pendaftaran mitra afiliasi) tetapi belum disetel toko di ERP.
     */
    private function customerFor(Order $order, int $tier): ?int
    {
        $id = (int) ($order->user?->erp_customer_id ?? 0);
        if ($id < 1) {
            return null;
        }
        try {
            $c = $this->erp->customer($id);
        } catch (\RuntimeException $e) {
            Log::warning('ERP: pelanggan pesanan tidak ditemukan', ['order' => $order->order_number, 'pelanggan_id' => $id, 'error' => $e->getMessage()]);

            return null;
        }
        if (($c['tipe_harga'] ?? null) !== ErpCatalog::tierName($tier) || ! ($c['aktif'] ?? true)) {
            Log::warning('ERP: tipe harga member beda dengan pesanan — nota dibuat tanpa pelanggan', [
                'order' => $order->order_number, 'pelanggan_id' => $id, 'tipe_erp' => $c['tipe_harga'] ?? null, 'tipe_pesanan' => ErpCatalog::tierName($tier)]);

            return null;
        }

        return $id;
    }
}
