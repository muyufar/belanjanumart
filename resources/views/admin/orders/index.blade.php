@extends('layouts.app')

@section('title', 'Admin Pesanan')

@section('content')
    <h1>Pesanan marketplace</h1>
    @foreach($orders as $o)
        <div class="card" style="padding:12px;margin-bottom:10px">
            <strong>{{ $o->order_number }}</strong>
            <div class="muted">{{ $o->customer_name }} · {{ \App\Services\OrderTrackingService::label($o->tracking_status) }}</div>
            <div>Rp {{ number_format($o->grand_total, 0, ',', '.') }} — {{ $o->fulfillment_label }}</div>
            <div class="muted">Status: {{ $o->status }} · {{ $o->payment_method }}@if($o->numart_source) · sumber {{ strtoupper($o->numart_source) }}@endif</div>
            @if($o->numart_invoice)<div class="muted">INV: {{ $o->numart_invoice }}</div>@endif
            <a href="{{ route('orders.show', $o) }}">Detail</a>
            @if(! $o->numart_invoice && in_array($o->status, ['proof_submitted', 'pending_cod'], true))
                <form method="POST" action="{{ route('admin.orders.confirm', $o) }}" style="display:inline" onsubmit="return confirm('Konfirmasi lunas & buat nota di toko?')">
                    @csrf
                    <button type="submit" class="btn btn-sm">Konfirmasi lunas</button>
                </form>
            @endif
            @if(! $o->numart_invoice && in_array($o->status, ['pending_transfer', 'proof_submitted', 'pending_cod'], true))
                <form method="POST" action="{{ route('admin.orders.cancel', $o) }}" style="display:inline" onsubmit="return confirm('Batalkan pesanan & lepas reservasi stok?')">
                    @csrf
                    <button type="submit" class="btn btn-sm">Batalkan</button>
                </form>
            @endif
        </div>
    @endforeach
    {{ $orders->links() }}
@endsection
