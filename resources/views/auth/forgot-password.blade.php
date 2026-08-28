@extends('layouts.app')

@section('page_class', 'page--auth')
@section('title', 'Lupa Password')

@section('content')
    <div class="auth-card">
        <h1>Lupa password</h1>

        @if(($step ?? 'phone') === 'phone')
            <p class="muted">Masukkan nomor HP akun Anda. Password sementara baru akan dikirim via WhatsApp.</p>
            @if(session('activate_hint'))
                <p class="toast toast--err" style="margin:12px 0">
                    Belum punya akun? <a href="{{ route('activate') }}"><strong>Aktivasi akun</strong></a> dulu.
                </p>
            @endif
            <form method="post" action="{{ route('password.forgot.send') }}" style="margin-top:20px">
                @csrf
                <div class="field">
                    <label>No. HP / WhatsApp</label>
                    <input type="tel" name="phone" value="{{ old('phone', $prefillPhone ?? '') }}" placeholder="08xxxxxxxxxx" required>
                    @error('phone')<span class="muted">{{ $message }}</span>@enderror
                </div>
                <button class="btn block" type="submit">Kirim password baru via WhatsApp</button>
            </form>
        @elseif(($step ?? '') === 'choose')
            <p class="muted">Beberapa akun ditemukan untuk nomor ini. Pilih data customer Anda:</p>
            <form method="post" action="{{ route('password.forgot.choose') }}" style="margin-top:16px">
                @csrf
                <ul class="activate-choices">
                    @foreach($candidates as $row)
                        <li>
                            <label class="activate-choice">
                                <input type="radio" name="customer_id" value="{{ $row->customer->customer_id }}" required>
                                <span>
                                    <strong>{{ $row->customer->customer_nama }}</strong><br>
                                    <span class="muted">{{ $row->cabang_label }}
                                        @if($row->customer->customer_kartu) · {{ $row->customer->customer_kartu }}@endif
                                    </span>
                                </span>
                            </label>
                        </li>
                    @endforeach
                </ul>
                <button class="btn block" type="submit">Reset password</button>
            </form>
        @else
            <div class="toast toast--ok">Password baru dikirim ke WhatsApp @if(!empty($maskedPhone)){{ $maskedPhone }}@endif</div>
            <p class="muted" style="margin-top:12px">
                <strong>{{ $customer->customer_nama }}</strong>
                @if(!empty($cabangLabel)) · {{ $cabangLabel }}@endif
            </p>
            <ol class="muted" style="font-size:0.85rem;padding-left:18px;margin:16px 0 0">
                <li>Masuk dengan password dari WhatsApp</li>
                <li>Segera ganti password di menu Ganti Password</li>
            </ol>
            <a href="{{ route('login') }}" class="btn block" style="margin-top:16px">Ke halaman masuk</a>
        @endif
    </div>
    <p class="muted" style="text-align:center;margin-top:16px">
        <a href="{{ route('login') }}">Kembali ke masuk</a>
    </p>
@endsection
