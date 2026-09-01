<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderTrackingService
{
    public const AWAITING_PAYMENT = 'awaiting_payment';

    public const PREPARING = 'preparing';

    public const QUEUED_FOR_DELIVERY = 'queued_for_delivery';

    public const OUT_FOR_DELIVERY = 'out_for_delivery';

    public const DELIVERED = 'delivered';

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::AWAITING_PAYMENT => 'Menunggu pembayaran',
            self::PREPARING => 'Barang sedang disiapkan',
            self::QUEUED_FOR_DELIVERY => 'Barang menunggu antrean dikirim',
            self::OUT_FOR_DELIVERY => 'Barang sedang dikirim',
            self::DELIVERED => 'Barang sudah sampai lokasi',
        ];
    }

    /** @return list<string> */
    public static function steps(): array
    {
        return [
            self::AWAITING_PAYMENT,
            self::PREPARING,
            self::QUEUED_FOR_DELIVERY,
            self::OUT_FOR_DELIVERY,
            self::DELIVERED,
        ];
    }

    public static function label(?string $status): string
    {
        if ($status === null || $status === '') {
            return '—';
        }

        return self::labels()[$status] ?? $status;
    }

    public static function stepIndex(?string $status): int
    {
        $idx = array_search($status, self::steps(), true);

        return $idx === false ? 0 : (int) $idx;
    }

    public static function fromPaymentStatus(string $paymentStatus): string
    {
        if (in_array($paymentStatus, ['pending_transfer', 'pending_cod', 'proof_submitted', 'pending_payment'], true)) {
            return self::AWAITING_PAYMENT;
        }

        if ($paymentStatus === 'processing') {
            return self::PREPARING;
        }

        return self::AWAITING_PAYMENT;
    }

    public static function fromPosKurir(int $kurirId, int $statusKurir): string
    {
        if ($statusKurir === 3) {
            return self::DELIVERED;
        }

        if ($statusKurir === 2) {
            return self::OUT_FOR_DELIVERY;
        }

        if ($kurirId > 0 && $statusKurir === 1) {
            return self::QUEUED_FOR_DELIVERY;
        }

        return self::PREPARING;
    }

    public function updateTracking(Order $order, string $trackingStatus, ?string $note = null): Order
    {
        if (! array_key_exists($trackingStatus, self::labels())) {
            throw new \InvalidArgumentException('Status pengiriman tidak valid.');
        }

        $order->update([
            'tracking_status' => $trackingStatus,
            'tracking_updated_at' => now(),
            'tracking_note' => $note,
        ]);

        return $order->fresh();
    }

    /**
     * Sinkronkan dari invoice POS (invoice_marketplace + kurir).
     */
    public function syncFromNumartInvoice(Order $order): Order
    {
        if (! $order->numart_invoice) {
            return $order;
        }

        $invoice = DB::connection('numart')
            ->table('invoice')
            ->leftJoin('user', 'user.user_id', '=', 'invoice.invoice_kurir')
            ->where('penjualan_invoice', $order->numart_invoice)
            ->select([
                'invoice.invoice_kurir',
                'invoice.invoice_status_kurir',
                'user.user_nama as kurir_nama',
            ])
            ->first();

        if (! $invoice) {
            return $order;
        }

        $kurirId = (int) ($invoice->invoice_kurir ?? 0);
        $statusKurir = (int) ($invoice->invoice_status_kurir ?? 1);
        $trackingStatus = self::fromPosKurir($kurirId, $statusKurir);

        $note = null;
        if ($kurirId > 0 && ! empty($invoice->kurir_nama)) {
            $note = 'Kurir: '.$invoice->kurir_nama;
        }

        if ($order->tracking_status !== $trackingStatus
            || ($note && $order->tracking_note !== $note)) {
            $order->update([
                'tracking_status' => $trackingStatus,
                'tracking_updated_at' => now(),
                'tracking_note' => $note ?? $order->tracking_note,
            ]);

            return $order->fresh();
        }

        return $order;
    }

    /** @return array<int, array{key: string, label: string, done: bool, active: bool}> */
    public function timelineSteps(Order $order): array
    {
        $currentIdx = self::stepIndex($order->tracking_status ?: self::fromPaymentStatus($order->status));
        $steps = [];

        foreach (self::steps() as $idx => $key) {
            $steps[] = [
                'key' => $key,
                'label' => self::label($key),
                'done' => $idx < $currentIdx,
                'active' => $idx === $currentIdx,
            ];
        }

        return $steps;
    }
}
