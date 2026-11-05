@extends('layouts.app')

@section('title', 'Mitra terdaftar')
@section('page_class', 'page--affiliate')

@section('content')
    <div class="section-head">
        <h2 style="margin:0">Mitra saya</h2>
        <a href="{{ route('affiliate.referrals.create') }}" class="btn" style="font-size:0.85rem;padding:8px 14px">+ Daftar</a>
    </div>

    @if($referrals->isEmpty())
        <div class="panel"><p class="muted" style="margin:0">Belum ada mitra warung / prioritas terdaftar.</p></div>
    @else
        <ul class="history-list">
            @foreach($referrals as $r)
                <li>
                    <strong>{{ $r->customer_name }}</strong>
                    <span class="badge-discount">{{ $r->partner_kind === 'prioritas' ? 'Prioritas' : 'Warung' }}</span>
                    <div class="muted" style="font-size:0.8rem;margin-top:4px">
                        Kartu {{ $r->member_card }} · {{ $r->customer_phone }}
                        @if($r->warung_name) · {{ $r->warung_name }} @endif
                    </div>
                </li>
            @endforeach
        </ul>
        {{ $referrals->links() }}
    @endif

    <p style="margin-top:16px"><a href="{{ route('affiliate.dashboard') }}">← Kembali ke panel</a></p>
@endsection
