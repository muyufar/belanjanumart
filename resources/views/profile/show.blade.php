@extends('layouts.app')

@section('title', 'Profil Saya')

@section('content')
    <div class="section-head">
        <h2 style="margin:0;font-size:1.25rem">Profil</h2>
    </div>

    @if($customer)
        <div class="digital-card">
            <div class="digital-card__brand">{{ config('marketplace.name') }}</div>
            <div class="digital-card__name">{{ $customer->customer_nama }}</div>
            <div class="digital-card__meta">
                <span>WA: {{ $customer->customer_tlpn }}</span>
                <span>No. kartu: <strong>{{ $customer->customer_kartu ?: '—' }}</strong></span>
            </div>
            @if($customer->customer_kartu)
                <svg id="memberBarcode" class="digital-card__barcode"></svg>
            @endif
            <div class="digital-card__points">Total poin: <strong>{{ number_format($points, 0, ',', '.') }}</strong></div>
        </div>
        @auth
            <form method="post" action="{{ route('logout') }}" style="margin-top:12px">
                @csrf
                <button type="submit" class="btn secondary block">Keluar</button>
            </form>
        @endauth
    @else
        <div class="panel"><p class="muted">Hubungkan akun dengan aktivasi nomor HP toko.</p></div>
    @endif

    <div class="section-head" style="margin-top:28px">
        <h2>Riwayat belanja</h2>
    </div>
    @if($history->isEmpty())
        <div class="panel"><p class="muted" style="margin:0">Belum ada transaksi.</p></div>
    @else
        <ul class="history-list">
            @foreach($history as $inv)
                <li>
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px">
                        <div>
                            <strong>{{ $inv->penjualan_invoice }}</strong>
                            @if($inv->invoice_marketplace)<span class="badge-discount">Online</span>@endif
                            <div class="muted" style="font-size:0.8rem;margin-top:4px">{{ $inv->invoice_tgl }}</div>
                        </div>
                        <div class="product-card__price" style="white-space:nowrap">Rp {{ number_format((int) $inv->invoice_sub_total, 0, ',', '.') }}</div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
@endsection

@push('scripts')
@if($customer && $customer->customer_kartu)
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>
    JsBarcode("#memberBarcode", @json($customer->customer_kartu), {
        format: "CODE128", width: 2, height: 64, displayValue: true, fontSize: 14, margin: 8
    });
</script>
@endif
@endpush
