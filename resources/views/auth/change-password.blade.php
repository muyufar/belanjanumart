@extends('layouts.app')

@section('title', 'Ganti Password')

@section('content')
    <div class="auth-card">
        <h1>Ganti password</h1>
        <p class="muted">Password sementara dari WhatsApp sebagai password saat ini.</p>
        <form method="post" action="{{ route('password.change.store') }}" style="margin-top:20px">
            @csrf
            <div class="field">
                <label>Password saat ini</label>
                <input type="password" name="current_password" required>
                @error('current_password')<span class="muted">{{ $message }}</span>@enderror
            </div>
            <div class="field">
                <label>Password baru</label>
                <input type="password" name="password" required>
            </div>
            <div class="field">
                <label>Ulangi password baru</label>
                <input type="password" name="password_confirmation" required>
                @error('password')<span class="muted">{{ $message }}</span>@enderror
            </div>
            <button class="btn block" type="submit">Simpan</button>
        </form>
    </div>
@endsection
