@extends('layouts.app')

@section('title', 'Masuk')

@section('content')
    <div class="auth-card">
        <h1>Masuk</h1>
        <p class="muted">Nomor HP terdaftar di Numart &amp; password akun online.</p>

        @if(session('activate_hint'))
            <p class="toast toast--ok" style="margin:12px 0">
                <a href="{{ route('activate') }}"><strong>Aktivasi akun</strong></a> untuk password pertama.
            </p>
        @endif

        <form method="post" action="{{ route('login') }}" style="margin-top:20px">
            @csrf
            <div class="field">
                <label>No. HP / WhatsApp</label>
                <input type="tel" name="phone" value="{{ old('phone') }}" placeholder="08xxxxxxxxxx" required>
                @error('phone')<span class="muted">{{ $message }}</span>@enderror
            </div>
            <div class="field">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                    <label style="margin:0">Password</label>
                    <a href="{{ route('password.forgot') }}" style="font-size:0.8rem;font-weight:600;color:var(--primary)">Lupa password?</a>
                </div>
                <input type="password" name="password" required>
            </div>
            <label class="muted" style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="remember"> Ingat saya</label>
            <button class="btn block" type="submit" style="margin-top:16px">Masuk</button>
        </form>
    </div>
    <p class="muted" style="text-align:center;margin-top:16px">
        <a href="{{ route('password.forgot') }}">Lupa password</a> ·
        <a href="{{ route('activate') }}">Aktivasi</a> ·
        <a href="{{ route('register') }}">Daftar</a>
    </p>
@endsection
