@extends('layouts.app')

@section('title', 'Pesanan '.$order->order_number)

@section('content')
    <div class="section-head">
        <h2 style="margin:0;font-size:1.15rem">{{ $order->order_number }}</h2>
        <span class="tier-pill">{{ str_replace('_', ' ', $order->status) }}</span>
    </div>

    <div class="panel" style="margin-bottom:14px">
        <p class="muted" style="margin:0 0 6px">{{ $order->fulfillment_label }}</p>
        @if($order->numart_invoice)
            <p class="muted" style="margin:0">Invoice: <strong>{{ $order->numart_invoice }}</strong></p>
        @endif
    </div>

    <div class="panel">
        @foreach($order->items as $item)
            <div class="cart-item">
                <span>{{ $item->barang_nama }} × {{ $item->qty }}</span>
                <span>Rp {{ number_format($item->line_total, 0, ',', '.') }}</span>
            </div>
        @endforeach
        <div class="summary-row total">
            <span>Total bayar</span>
            <span>Rp {{ number_format($order->grand_total, 0, ',', '.') }}</span>
        </div>
    </div>

    @if($order->payment && !$order->isPaid())
        <div class="va-box">
            <div>Virtual Account BRI</div>
            <div class="number">{{ $order->payment->virtual_account }}</div>
            <div style="margin-top:8px;font-size:.85rem">Berlaku sampai {{ $order->expires_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }}</div>
        </div>
        <form method="post" action="{{ route('orders.check-payment', $order) }}" style="margin-top:12px">
            @csrf
            <button type="submit" class="btn block">Cek pembayaran</button>
        </form>
        @if(config('bri.mock'))
            <p class="muted" style="margin-top:8px;text-align:center">Mode mock: simulasi lunas.</p>
        @endif
    @elseif($order->isPaid())
        <div class="toast toast--ok" style="margin-top:16px">Pembayaran diterima. Pesanan diproses.</div>
    @endif
@endsection
