@extends('layouts.app')

@section('title', 'Pesanan '.$order->order_number)

@section('content')
    <div class="section-head">
        <h2 style="margin:0;font-size:1.15rem">{{ $order->order_number }}</h2>
        <span class="tier-pill">{{ str_replace('_', ' ', $order->status) }}</span>
    </div>

    <div class="panel" style="margin-bottom:14px">
        <p class="muted" style="margin:0 0 6px">{{ $order->fulfillment_label }}</p>
        <p class="muted" style="margin:0">Metode: <strong>{{ strtoupper($order->payment_method) }}</strong></p>
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

    @if($order->payment_method === 'cod')
        @if($waOrderUrl)
            <a href="{{ $waOrderUrl }}" target="_blank" rel="noopener" class="btn block" style="margin-top:16px">
                Kirim pesanan via WhatsApp
            </a>
        @else
            <p class="toast toast--err" style="margin-top:16px">Nomor WhatsApp cabang belum dikonfigurasi.</p>
        @endif
        <p class="muted" style="margin-top:8px;text-align:center;font-size:0.85rem">Admin cabang akan memproses pesanan dan mengirim nota via WhatsApp.</p>
    @else
        @if($qrisUrl)
            <div class="panel" style="margin-top:16px;text-align:center">
                <p style="font-weight:700;margin:0 0 8px">Scan QRIS — Rp {{ number_format($order->grand_total, 0, ',', '.') }}</p>
                <img src="{{ $qrisUrl }}" alt="QRIS" style="max-width:260px;width:100%;border-radius:12px">
            </div>
        @else
            <p class="toast toast--err" style="margin-top:16px">QRIS cabang belum dikonfigurasi di sistem.</p>
        @endif

        @if(in_array($order->status, ['pending_transfer', 'proof_submitted'], true))
            @if($order->payment_proof_path)
                <p class="muted" style="margin-top:12px;text-align:center">Bukti transfer sudah diupload.</p>
            @else
                <form method="post" action="{{ route('orders.upload-proof', $order) }}" enctype="multipart/form-data" style="margin-top:16px" class="panel">
                    @csrf
                    <div class="field">
                        <label>Upload bukti transfer</label>
                        <input type="file" name="payment_proof" accept="image/*" required>
                    </div>
                    <button type="submit" class="btn block">Upload bukti</button>
                </form>
            @endif

            @if($waProofUrl && $order->payment_proof_path)
                <a href="{{ $waProofUrl }}" target="_blank" rel="noopener" class="btn block" style="margin-top:12px">
                    Kirim konfirmasi via WhatsApp
                </a>
            @elseif($waOrderUrl && !$order->payment_proof_path)
                <a href="{{ $waOrderUrl }}" target="_blank" rel="noopener" class="btn block btn--ghost" style="margin-top:12px">
                    Tanya cabang via WhatsApp
                </a>
            @endif
        @endif
    @endif
@endsection
