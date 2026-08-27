@extends('layouts.app')

@section('title', 'Verifikasi Member')

@section('content')
    <div class="section-head">
        <h2>Verifikasi Member</h2>
    </div>

    @if(session('success'))
        <div class="toast toast--ok">{{ session('success') }}</div>
    @endif

    @if($members->isEmpty())
        <div class="panel" style="text-align:center;padding:32px">
            <p class="muted" style="margin:0">Tidak ada pengajuan verifikasi menunggu.</p>
        </div>
    @else
        @foreach($members as $member)
            <div class="panel" style="margin-bottom:12px">
                <p style="margin:0 0 4px"><strong>{{ $member->name }}</strong> — {{ $member->member_card }}</p>
                <p class="muted" style="margin:0 0 8px;font-size:0.85rem">
                    Tier: {{ $member->price_tier === 2 ? 'Grosir' : 'Retail' }} ·
                    Cabang: {{ $member->member_cabang }}
                </p>
                <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:12px">
                    @if($member->ktp_path)
                        <a href="{{ asset('storage/'.$member->ktp_path) }}" target="_blank">Lihat KTP</a>
                    @endif
                    @if($member->business_photo_path)
                        <a href="{{ asset('storage/'.$member->business_photo_path) }}" target="_blank">Lihat tempat usaha</a>
                    @endif
                </div>
                <form method="post" action="{{ route('admin.members.approve', $member) }}" style="display:inline">
                    @csrf
                    <button type="submit" class="btn">Setujui</button>
                </form>
                <form method="post" action="{{ route('admin.members.reject', $member) }}" style="display:inline">
                    @csrf
                    <button type="submit" class="btn btn--ghost">Tolak</button>
                </form>
            </div>
        @endforeach
    @endif
@endsection
