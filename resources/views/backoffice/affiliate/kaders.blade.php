@extends('layouts.backoffice')

@section('title', 'Mitra kader')

@section('content')
    <h1 style="margin-top:0">Mitra kader / affiliate</h1>
    <p class="muted">Daftarkan kader di sini. Mereka login di toko dengan <strong>nomor kartu member</strong> dan kode referral untuk member.</p>

    <div class="panel" style="margin:16px 0">
        <h2 style="margin:0 0 12px;font-size:1rem">Tambah dari kartu POS</h2>
        <form method="post" action="{{ route('backoffice.affiliate.kaders.store') }}">
            @csrf
            <div class="field">
                <label>Nomor kartu member</label>
                <input type="text" name="member_card" placeholder="NUBLJ00001234">
            </div>
            <button type="submit" class="btn">Jadikan mitra kader</button>
        </form>
    </div>

    <div class="panel" style="margin-bottom:16px">
        <h2 style="margin:0 0 12px;font-size:1rem">Atau buat member baru + kader</h2>
        <form method="post" action="{{ route('backoffice.affiliate.kaders.store') }}">
            @csrf
            <div class="field"><label>Nama</label><input type="text" name="name"></div>
            <div class="field"><label>HP / WA</label><input type="tel" name="phone"></div>
            <div class="field"><label>Alamat</label><textarea name="address" rows="2"></textarea></div>
            <button type="submit" class="btn secondary">Buat &amp; jadikan kader</button>
        </form>
    </div>

    @foreach($kaders as $k)
        <div class="panel" style="margin-bottom:10px;padding:14px">
            <strong>{{ $k->name }}</strong>
            <span class="tier-pill">{{ $k->affiliate_code }}</span>
            @if(!$k->affiliate_active)<span class="badge-discount">Nonaktif</span>@endif
            <div class="muted">{{ $k->member_card }} · {{ $k->phone }}</div>
            <div class="muted">Member: {{ $k->referredMembers()->count() }}</div>
            <form method="post" action="{{ route('backoffice.affiliate.kaders.toggle', $k) }}" style="margin-top:8px">
                @csrf
                <button type="submit" class="btn secondary">{{ $k->affiliate_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
            </form>
        </div>
    @endforeach

    {{ $kaders->links() }}
@endsection
