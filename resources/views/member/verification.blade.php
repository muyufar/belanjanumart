@extends('layouts.app')

@section('title', 'Verifikasi Akun')

@section('content')
    <div class="section-head">
        <h2 style="margin:0;font-size:1.2rem">Verifikasi Member</h2>
    </div>

    <div class="panel">
        <p class="muted" style="margin-top:0">
            Upload dokumen untuk verifikasi COD.
            @if($isGrosir)
                Member grosir: KTP + foto tempat usaha.
            @else
                Member retail: KTP.
            @endif
        </p>

        @if($user->member_verification_status === 'rejected')
            <div class="toast toast--err" style="margin-bottom:12px">Verifikasi sebelumnya ditolak. Silakan upload ulang.</div>
        @endif

        <form method="post" action="{{ route('member.verification.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="field">
                <label>Foto KTP</label>
                <input type="file" name="ktp" accept="image/*" required>
            </div>
            @if($isGrosir)
                <div class="field">
                    <label>Foto tempat usaha</label>
                    <input type="file" name="business_photo" accept="image/*" required>
                </div>
            @endif
            <button class="btn block" type="submit">Kirim verifikasi</button>
        </form>
    </div>
@endsection
