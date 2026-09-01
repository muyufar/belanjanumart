@extends('layouts.app')

@section('title', 'Admin Pesanan')

@section('content')
    <h1>Pesanan marketplace</h1>
    @foreach($orders as $o)
        <div class="card" style="padding:12px;margin-bottom:10px">
            <strong>{{ $o->order_number }}</strong>
            <div class="muted">{{ $o->customer_name }} · {{ \App\Services\OrderTrackingService::label($o->tracking_status) }}</div>
            <div>Rp {{ number_format($o->grand_total, 0, ',', '.') }} — {{ $o->fulfillment_label }}</div>
            @if($o->numart_invoice)<div class="muted">INV: {{ $o->numart_invoice }}</div>@endif
            <a href="{{ route('orders.show', $o) }}">Detail</a>
        </div>
    @endforeach
    {{ $orders->links() }}
@endsection
