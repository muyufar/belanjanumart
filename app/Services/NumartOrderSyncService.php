<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class NumartOrderSyncService
{
    /**
     * Tulis invoice + penjualan ke Numart setelah pembayaran sukses.
     */
    public function syncPaidOrder(Order $order): string
    {
        if ($order->numart_invoice) {
            return $order->numart_invoice;
        }

        $order->load('items');
        $cabang = (int) $order->fulfillment_cabang;
        $kasirId = $this->resolveMarketplaceKasirUserId();
        $customerId = (int) config('marketplace.numart_marketplace_customer_id', 1);

        if ($order->user?->numart_customer_id) {
            $customerId = (int) $order->user->numart_customer_id;
        }

        $invoiceNo = $this->nextInvoiceNumber($cabang, $kasirId);
        $invoiceCount = $this->nextInvoiceCount($cabang);
        $today = Carbon::now('Asia/Jakarta');
        $tgl = $today->format('d F Y g:i:s a');
        $date = $today->format('Y-m-d');
        $ym = $today->format('Y-m');

        $totalBeli = $order->items->sum(fn ($i) => $i->harga_beli * $i->qty);
        $total = $order->subtotal;
        $ongkir = $order->shipping_fee;
        $diskon = $order->discount;
        $subTotal = $order->grand_total;

        DB::connection('numart')->table('invoice')->insert([
            'penjualan_invoice' => $invoiceNo,
            'penjualan_invoice_count' => (string) $invoiceCount,
            'invoice_tgl' => $tgl,
            'invoice_customer' => (string) $customerId,
            'invoice_customer_category' => (int) $order->price_tier,
            'invoice_kurir' => '0',
            'invoice_status_kurir' => 1,
            'status' => 2,
            'invoice_tipe_transaksi' => 1,
            'invoice_total_beli' => $totalBeli,
            'invoice_total' => $total,
            'invoice_ongkir' => $ongkir,
            'invoice_diskon' => $diskon,
            'invoice_sub_total' => $subTotal,
            'invoice_bayar' => $subTotal,
            'invoice_kembali' => 0,
            'invoice_kasir' => (string) $kasirId,
            'invoice_date' => $date,
            'invoice_date_year_month' => $ym,
            'invoice_date_edit' => ' ',
            'invoice_kasir_edit' => ' ',
            'invoice_total_beli_lama' => $totalBeli,
            'invoice_total_lama' => (string) $total,
            'invoice_ongkir_lama' => $ongkir,
            'invoice_sub_total_lama' => $subTotal,
            'invoice_bayar_lama' => (string) $subTotal,
            'invoice_kembali_lama' => '0',
            'invoice_marketplace' => $order->order_number,
            'invoice_ekspedisi' => 0,
            'invoice_no_resi' => '-',
            'invoice_date_selesai_kurir' => '-',
            'invoice_piutang' => 0,
            'invoice_piutang_dp' => '0',
            'invoice_piutang_jatuh_tempo' => '0',
            'invoice_piutang_lunas' => 0,
            'invoice_draft' => 0,
            'invoice_cabang' => $cabang,
        ]);

        foreach ($order->items as $item) {
            $qtyKeranjang = $item->qty * $item->konversi_isi;

            DB::connection('numart')->table('penjualan')->insert([
                'penjualan_barang_id' => $item->barang_id,
                'barang_id' => $item->barang_id,
                'barang_qty' => $item->qty,
                'barang_qty_keranjang' => $qtyKeranjang,
                'barang_qty_konversi_isi' => $item->konversi_isi,
                'keranjang_satuan' => $item->satuan_id,
                'keranjang_harga_beli' => (string) $item->harga_beli,
                'keranjang_harga' => (string) $item->unit_price,
                'keranjang_harga_parent' => $item->unit_price,
                'keranjang_harga_edit' => 0,
                'keranjang_id_kasir' => $kasirId,
                'penjualan_invoice' => $invoiceNo,
                'penjualan_date' => $date,
                'penjualan_date_year_month' => $ym,
                'barang_qty_lama' => (string) $item->qty,
                'barang_qty_lama_parent' => (string) $item->qty,
                'barang_option_sn' => 0,
                'barang_sn_id' => 0,
                'barang_sn_desc' => '0',
                'invoice_customer_category' => (int) $order->price_tier,
                'penjualan_cabang' => $cabang,
            ]);

            DB::connection('numart')->table('terlaris')->insert([
                'barang_id' => $item->barang_id,
                'barang_terjual' => $item->qty,
            ]);
        }

        $order->update([
            'numart_invoice' => $invoiceNo,
            'status' => 'processing',
        ]);

        return $invoiceNo;
    }

    protected function nextInvoiceCount(int $cabang): int
    {
        $last = DB::connection('numart')
            ->table('invoice')
            ->where('invoice_cabang', $cabang)
            ->orderByDesc('invoice_id')
            ->value('penjualan_invoice_count');

        return ((int) $last) + 1;
    }

    protected function nextInvoiceNumber(int $cabang, int $kasirId): string
    {
        $count = $this->nextInvoiceCount($cabang);

        return date('YmdHis').$count.$kasirId;
    }

    /**
     * User kasir virtual "Belanja Online" di tabel user Numart.
     */
    protected function resolveMarketplaceKasirUserId(): int
    {
        $label = (string) config('marketplace.kasir_label', 'Belanja Online');
        $configured = (int) config('marketplace.numart_kasir_id', 0);

        if ($configured > 0) {
            $exists = DB::connection('numart')
                ->table('user')
                ->where('user_id', $configured)
                ->where('user_status', '1')
                ->value('user_id');

            if ($exists) {
                return (int) $exists;
            }
        }

        $byName = DB::connection('numart')
            ->table('user')
            ->where('user_nama', $label)
            ->where('user_status', '1')
            ->value('user_id');

        if ($byName) {
            return (int) $byName;
        }

        $now = Carbon::now('Asia/Jakarta')->format('d F Y g:i:s a');
        $email = 'belanja.online@numart.local';
        $password = md5(md5('belanja-online-'.config('app.key')));

        DB::connection('numart')->table('user')->insert([
            'user_nama' => $label,
            'user_no_hp' => '0',
            'user_alamat' => '-',
            'user_email' => $email,
            'user_password' => $password,
            'user_create' => $now,
            'user_level' => 'kasir',
            'user_status' => '1',
            'user_cabang' => 0,
        ]);

        return (int) DB::connection('numart')->getPdo()->lastInsertId();
    }
}
