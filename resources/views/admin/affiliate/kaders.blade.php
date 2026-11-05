@extends('layouts.app')

@section('title', 'Mitra Kader — Super Admin')

@section('content')
    <h1>Mitra Kader NU (Affiliate)</h1>
    <p class="muted">Hanya super admin yang dapat menambah / menonaktifkan mitra kader.</p>

    <div class="panel" style="margin:16px 0">
        <form method="post" action="{{ route('admin.affiliate.kaders.store') }}">
            @csrf
            <div class="field">
                <label>Nomor kartu member POS (calon kader)</label>
                <input type="text" name="member_card" required placeholder="NUBLJ00001234">
            </div>
            <button type="submit" class="btn">Jadikan mitra kader</button>
        </form>
    </div>

    @foreach($kaders as $k)
        <div class="card panel" style="padding:12px;margin-bottom:10px">
            <strong>{{ $k->name }}</strong>
            <span class="tier-pill">{{ $k->affiliate_code }}</span>
            @if(!$k->affiliate_active)<span class="badge-discount">Nonaktif</span>@endif
            <div class="muted">{{ $k->member_card }} · {{ $k->phone }}</div>
            <div class="muted">Mitra terdaftar: {{ $k->referredMembers()->count() }}</div>
            <form method="post" action="{{ route('admin.affiliate.kaders.toggle', $k) }}" style="margin-top:8px">
                @csrf
                <button type="submit" class="btn secondary">{{ $k->affiliate_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
            </form>
        </div>
    @endforeach

    {{ $kaders->links() }}

    <p style="margin-top:20px">
        <a href="{{ route('admin.affiliate.settings') }}">Pengaturan komisi</a> ·
        <a href="{{ route('admin.affiliate.commissions') }}">Daftar komisi</a>
    </p>
@endsection
