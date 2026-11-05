@extends('layouts.backoffice')

@section('title', 'Komisi')

@section('content')
    <h1 style="margin-top:0">Pembayaran komisi kader</h1>
    <p>
        <a href="{{ route('backoffice.affiliate.commissions', ['status' => 'accrued']) }}">Menunggu</a> ·
        <a href="{{ route('backoffice.affiliate.commissions', ['status' => 'paid']) }}">Sudah dibayar</a>
    </p>

    @if($commissions->isEmpty())
        <div class="panel">
            <p class="muted" style="margin:0 0 8px">Belum ada komisi dengan status ini.</p>
            <p class="muted" style="margin:0;font-size:0.85rem">
                Komisi muncul setelah pesanan <strong>member referral</strong> lunas (status processing).
                Jika bayar dikonfirmasi dari POS, jalankan di server belanja:
                <code style="display:block;margin-top:6px">php artisan affiliate:accrue-pending</code>
            </p>
        </div>
    @endif

    @foreach($commissions as $c)
        <div class="panel" style="margin-bottom:10px;padding:12px">
            <strong>{{ $c->affiliate?->name }}</strong> · {{ $c->order?->order_number }}
            <div class="muted">{{ $c->referredUser?->name }} · margin Rp {{ number_format($c->order_net_margin, 0, ',', '.') }} · {{ $c->margin_share_percent }}%</div>
            <div>Komisi: Rp {{ number_format($c->commission_amount, 0, ',', '.') }}</div>
            @if($c->status === 'accrued')
                <form method="post" action="{{ route('backoffice.affiliate.commissions.pay', $c) }}" style="margin-top:8px">
                    @csrf
                    <input type="text" name="paid_note" placeholder="Catatan transfer" style="width:100%;margin-bottom:6px">
                    <button type="submit" class="btn secondary">Sudah dibayar</button>
                </form>
            @endif
        </div>
    @endforeach

    {{ $commissions->links() }}
@endsection
