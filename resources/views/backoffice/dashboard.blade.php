@extends('layouts.backoffice')

@section('title', 'Dashboard')

@section('content')
    <h1 style="margin-top:0">Dashboard affiliate</h1>
    <div class="affiliate-stats">
        <div class="panel affiliate-stat">
            <span class="muted">Mitra kader aktif</span>
            <strong>{{ $kaderCount }}</strong>
        </div>
        <div class="panel affiliate-stat">
            <span class="muted">Member under kader</span>
            <strong>{{ $referralCount }}</strong>
        </div>
        <div class="panel affiliate-stat">
            <span class="muted">Komisi menunggu bayar</span>
            <strong>Rp {{ number_format($commissionPending, 0, ',', '.') }}</strong>
        </div>
    </div>
@endsection
