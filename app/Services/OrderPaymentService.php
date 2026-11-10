<?php

namespace App\Services;

use App\Models\AffiliateCommission;
use App\Models\Order;
use App\Services\Erp\ErpOrders;

/**
 * Konfirmasi lunas & pembatalan pesanan — satu tempat untuk API POS lama (confirm-payment), admin belanjanumart,
 * webhook BRI, dan webhook ERP. Nota dibuat oleh NumartOrderSyncService di sistem toko yang aktif (NUMART_SOURCE).
 */
class OrderPaymentService
{
    public function __construct(
        protected NumartOrderSyncService $sync,
        protected AffiliateCommissionService $commissions,
    ) {}

    /**
     * Validasi status lalu buat nota. Bentuk hasil = respons JSON lama NumartOrderApiController::confirmPayment.
     *
     * @return array{0: int, 1: array<string, mixed>}
     */
    public function confirm(Order $order): array
    {
        $order->loadMissing(['items', 'user']);

        if ($order->numart_invoice) {
            return [200, ['success' => true, 'message' => 'Pesanan sudah masuk nota toko.', 'invoice' => $order->numart_invoice]];
        }

        if ($order->payment_method === 'transfer' && $order->status !== 'proof_submitted') {
            return [422, ['success' => false, 'message' => 'Transfer belum ada bukti upload atau sudah diproses.']];
        }

        if ($order->payment_method === 'cod' && $order->status !== 'pending_cod') {
            return [422, ['success' => false, 'message' => 'Pesanan COD tidak dalam status menunggu proses.']];
        }

        if (! in_array($order->status, ['proof_submitted', 'pending_cod'], true)) {
            return [422, ['success' => false, 'message' => 'Status pesanan tidak dapat dikonfirmasi.']];
        }

        if ($order->payment_method === 'transfer' && ! $order->payment_proof_path) {
            return [422, ['success' => false, 'message' => 'Bukti transfer belum diupload member.']];
        }

        return $this->markPaid($order);
    }

    /**
     * Tandai lunas tanpa cek bukti (mis. webhook pembayaran BRI) → nota + komisi.
     *
     * @return array{0: int, 1: array<string, mixed>}
     */
    public function markPaid(Order $order): array
    {
        try {
            $invoice = $this->sync->syncPaidOrder($order);
            $order->refresh();
            $this->commissions->accrueForOrder($order);
            if (! $order->paid_at) {
                $order->update(['paid_at' => now()]);
            }

            return [200, ['success' => true, 'message' => 'Pembayaran dikonfirmasi. Nota toko: '.$invoice, 'invoice' => $invoice]];
        } catch (\Throwable $e) {
            report($e);

            return [500, ['success' => false, 'message' => 'Gagal membuat nota di toko: '.$e->getMessage()]];
        }
    }

    /** Batalkan pesanan yang belum lunas: lepas reservasi stok ERP (StockHold lokal POS lama tidak pernah dilepas otomatis). */
    public function cancel(Order $order, ?string $note = null): Order
    {
        if ($order->numart_invoice) {
            throw new \InvalidArgumentException('Pesanan sudah punya nota toko — batalkan notanya di Backoffice ERP.');
        }
        if ($order->status === 'cancelled') {
            return $order;
        }

        if ($order->numart_source === 'erp') {
            app(ErpOrders::class)->release($order);
        }

        $order->update(['status' => 'cancelled', 'tracking_note' => $note ?? 'Pesanan dibatalkan']);

        return $order->fresh();
    }

    /** Webhook ERP `pesanan.dibatalkan`: nota online dibatalkan admin toko. */
    public function cancelledByStore(Order $order, ?string $reason): Order
    {
        $order->update([
            'status' => 'cancelled',
            'tracking_note' => 'Nota dibatalkan toko'.($reason ? ' — '.$reason : ''),
            'tracking_updated_at' => now(),
        ]);
        AffiliateCommission::where('order_id', $order->id)
            ->where('status', AffiliateCommission::STATUS_ACCRUED)
            ->update(['status' => AffiliateCommission::STATUS_CANCELLED]);

        return $order->fresh();
    }
}
