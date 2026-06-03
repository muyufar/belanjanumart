@extends('layouts.app')

@section('title', 'Daftar')

@section('content')
    <div class="auth-card">
        <h1>Daftar</h1>
        <p class="muted">Data langsung ke Numart, lalu aktivasi via WhatsApp.</p>
        <form method="post" action="{{ url('/daftar') }}" style="margin-top:20px">
            @csrf
            <div class="field"><label>Nama</label><input name="name" value="{{ old('name') }}" required>@error('name')<span class="muted">{{ $message }}</span>@enderror</div>
            <div class="field">
                <label>No. HP / WhatsApp</label>
                <input type="tel" name="phone" value="{{ old('phone') }}" required>
                @error('phone')<span class="muted">{{ $message }}</span>@enderror
            </div>
            <div class="field"><label>Email (opsional)</label><input type="email" name="email" value="{{ old('email') }}"></div>
            <div class="field"><label>Alamat</label><textarea name="address" rows="3">{{ old('address') }}</textarea></div>
            <button class="btn block" type="submit">Simpan &amp; aktivasi</button>
        </form>
    </div>
    <p class="muted" style="text-align:center;margin-top:16px">
        <a href="{{ route('activate') }}">Sudah terdaftar di toko?</a>
    </p>
@endsection
