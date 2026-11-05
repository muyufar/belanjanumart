@extends('layouts.app')

@section('title', 'Mitra Kader NU')
@section('page_class', 'page--affiliate')

@section('breadcrumb')
    @include('layouts.partials.breadcrumb', ['items' => [
        ['label' => 'Beranda', 'url' => route('shop.index')],
        ['label' => 'Mitra Kader'],
    ]])
@endsection

@section('content')
    <div class="section-head">
        <h2 style="margin:0;font-size:1.25rem">Panel Mitra Kader</h2>
        <span class="tier-pill">Kode {{ $kader->affiliate_code }}</span>
    </div>

    <div class="affiliate-stats">
        <div class="panel affiliate-stat">
            <span class="muted">Komisi menunggu dibayar</span>
            <strong>Rp {{ number_format($totals['accrued'], 0, ',', '.') }}</strong>
            <small class="muted">{{ $totals['count_accrued'] }} transaksi</small>
        </div>
        <div class="panel affiliate-stat">
            <span class="muted">Komisi sudah dibayar</span>
            <strong>Rp {{ number_format($totals['paid'], 0, ',', '.') }}</strong>
            <small class="muted">{{ $totals['count_paid'] }} transaksi</small>
        </div>
        <div class="panel affiliate-stat">
            <span class="muted">Bagi hasil margin bersih Numart</span>
            <strong>{{ $settings['margin_share_min'] }}% – {{ $settings['margin_share_max'] }}%</strong>
            <small class="muted">Dari margin bersih (subtotal − HPP), naik seiring margin lebih sehat</small>
        </div>
    </div>

    <div class="affiliate-actions" style="margin:16px 0;display:flex;flex-wrap:wrap;gap:10px">
        <a href="{{ route('affiliate.referrals.create') }}" class="btn">Daftar mitra baru</a>
        <a href="{{ route('affiliate.referrals') }}" class="btn secondary">Daftar mitra</a>
        <a href="{{ route('affiliate.orders') }}" class="btn secondary">Pantau belanja</a>
    </div>

    <div class="section-head"><h3 style="margin:0;font-size:1rem">Komisi terbaru</h3></div>
    @if($recentCommissions->isEmpty())
        <div class="panel"><p class="muted" style="margin:0">Belum ada komisi — mitra yang Anda daftarkan belum menyelesaikan pembayaran pesanan.</p></div>
    @else
        <ul class="history-list">
            @foreach($recentCommissions as $c)
                <li>
                    <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap">
                        <div>
                            <strong>{{ $c->order?->order_number ?? 'Pesanan #'.$c->order_id }}</strong>
                            <div class="muted" style="font-size:0.8rem">{{ $c->referredUser?->name }} · margin Rp {{ number_format($c->order_net_margin ?? 0, 0, ',', '.') }} · {{ $c->margin_share_percent ?? $c->commission_percent }}%</div>
                        </div>
                        <div style="text-align:right">
                            <div class="product-card__price">+ Rp {{ number_format($c->commission_amount, 0, ',', '.') }}</div>
                            <div class="muted" style="font-size:0.75rem">{{ $c->status === 'paid' ? 'Dibayar' : 'Menunggu admin' }}</div>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
