@extends('layouts.app')

@section('title', 'Belanja mitra')
@section('page_class', 'page--affiliate')

@section('content')
    <div class="section-head">
        <h2 style="margin:0">Pantau belanja mitra</h2>
    </div>

    @if($orders->isEmpty())
        <div class="panel"><p class="muted" style="margin:0">Belum ada pesanan dari mitra yang Anda daftarkan.</p></div>
    @else
        <ul class="my-orders-list">
            @foreach($orders as $order)
                @php($comm = $commissionByOrder->get($order->id))
                <li>
                    <div class="my-orders-list__item" style="cursor:default">
                        <div class="my-orders-list__main">
                            <div class="my-orders-list__top">
                                <strong>{{ $order->order_number }}</strong>
                                <span class="my-orders-list__badge">{{ str_replace('_', ' ', $order->status) }}</span>
                            </div>
                            <div class="my-orders-list__meta muted">
                                {{ $order->user?->name }} · {{ $order->created_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB
                            </div>
                            @if($comm)
                                <div class="my-orders-list__meta" style="color:var(--primary-dark);font-weight:700">
                                    Komisi Anda: Rp {{ number_format($comm->commission_amount, 0, ',', '.') }}
                                    ({{ $comm->commission_percent }}%)
                                </div>
                            @elseif(in_array($order->status, ['pending_transfer', 'pending_cod', 'proof_submitted'], true))
                                <div class="my-orders-list__meta muted">Komisi muncul setelah pembayaran diverifikasi.</div>
                            @endif
                        </div>
                        <div class="my-orders-list__side">
                            <span class="my-orders-list__total">Rp {{ number_format($order->grand_total, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
        {{ $orders->links() }}
    @endif

    <p style="margin-top:16px"><a href="{{ route('affiliate.dashboard') }}">← Kembali ke panel</a></p>
@endsection
