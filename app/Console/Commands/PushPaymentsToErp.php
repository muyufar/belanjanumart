<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderPaymentService;
use Illuminate\Console\Command;

/**
 * Kirim (ulang) pesanan yang menunggu dicek — bukti transfer sudah diunggah / COD — ke antrean
 * Backoffice ERP → Operasional → Pembayaran Online. Aman diulang. Jalankan sekali setelah fitur dipasang.
 */
class PushPaymentsToErp extends Command
{
    protected $signature = 'erp:kirim-pembayaran {--limit=200}';

    protected $description = 'Kirim pesanan yang menunggu verifikasi bayar (bukti transfer / COD) ke Backoffice ERP';

    public function handle(OrderPaymentService $payments): int
    {
        $orders = Order::query()->whereIn('status', ['proof_submitted', 'pending_cod'])->whereNull('numart_invoice')
            ->orderBy('id')->limit(max(1, (int) $this->option('limit')))->get();
        $ok = 0;
        foreach ($orders as $order) {
            if ($payments->pushToErp($order)) {
                $ok++;
                $this->line('terkirim '.$order->order_number);
            } else {
                $this->warn('gagal / dilewati '.$order->order_number.' (lihat log)');
            }
        }
        $this->info("Terkirim {$ok} dari {$orders->count()} pesanan.");

        return self::SUCCESS;
    }
}
