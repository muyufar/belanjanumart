<?php

namespace App\Services;

use App\Models\Order;

class BranchWhatsAppService
{
    public function __construct(
        protected MemberContextService $memberContext,
        protected NumartCustomerService $customers,
    ) {}

    public function webUrlForOrder(Order $order, string $context = 'order'): ?string
    {
        $phone = $this->memberContext->branchWhatsApp((int) $order->fulfillment_cabang);
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        $message = match ($context) {
            'transfer_proof' => $this->transferProofMessage($order),
            default => $this->orderMessage($order),
        };

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($message);
    }

    protected function orderMessage(Order $order): string
    {
        $order->loadMissing('items', 'user');
        $lines = ["*Pesanan Belanja Numart*", "No: {$order->order_number}"];

        if ($order->user?->member_card) {
            $lines[] = 'Kartu: '.$order->user->member_card;
        }

        $lines[] = 'Metode: '.strtoupper($order->payment_method);
        $lines[] = 'Cabang: '.$order->fulfillment_label;
        $lines[] = 'Nama: '.$order->customer_name;
        $lines[] = 'HP: '.$order->customer_phone;
        $lines[] = 'Alamat: '.$order->customer_address;
        $lines[] = '';
        $lines[] = '*Detail:*';

        foreach ($order->items as $item) {
            $lines[] = "- {$item->barang_nama} × {$item->qty} = Rp ".number_format($item->line_total, 0, ',', '.');
        }

        $lines[] = '';
        $lines[] = '*Total: Rp '.number_format($order->grand_total, 0, ',', '.').'*';

        if ($order->payment_method === 'cod') {
            $lines[] = 'Pembayaran: COD (bayar di tempat)';
        } else {
            $lines[] = 'Pembayaran: Transfer';
        }

        return implode("\n", $lines);
    }

    protected function transferProofMessage(Order $order): string
    {
        $base = $this->orderMessage($order);

        return $base."\n\nBukti transfer sudah diupload di sistem. Mohon diproses ya.";
    }
}
