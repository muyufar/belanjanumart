@extends('layouts.app')

@section('page_class', 'page--auth')
@section('title', 'Aktivasi Akun')

@section('content')
    <div class="auth-card">
        <h1>Aktivasi akun</h1>

        @if(($step ?? 'phone') === 'phone')
            <p class="muted">Nomor HP terdaftar di Numart (semua cabang). Password dikirim via WhatsApp.</p>
            <form method="post" action="{{ route('activate.lookup') }}" style="margin-top:20px">
                @csrf
                <div class="field">
                    <label>No. HP / WhatsApp</label>
                    <input type="tel" name="phone" value="{{ old('phone', $prefillPhone ?? '') }}" placeholder="08xxxxxxxxxx" required>
                    @error('phone')<span class="muted">{{ $message }}</span>@enderror
                </div>
                <button class="btn block" type="submit">Kirim password via WhatsApp</button>
            </form>
        @elseif(($step ?? '') === 'choose')
            <p class="muted">Beberapa data customer ditemukan. Pilih yang benar:</p>
            <form method="post" action="{{ route('activate.choose') }}" style="margin-top:16px">
                @csrf
                <ul class="activate-choices">
                    @foreach($candidates as $row)
                        <li>
                            <label class="activate-choice">
                                <input type="radio" name="customer_id" value="{{ $row->customer->customer_id }}" required>
                                <span>
                                    <strong>{{ $row->customer->customer_nama }}</strong><br>
                                    <span class="muted">Cabang: {{ $row->cabang_label }}
                                        @if($row->customer->customer_kartu) · {{ $row->customer->customer_kartu }}@endif
                                    </span>
                                </span>
                            </label>
                        </li>
                    @endforeach
                </ul>
                <button class="btn block" type="submit">Kirim password</button>
            </form>
        @else
            <div class="toast toast--ok">Password dikirim ke WhatsApp @if(!empty($maskedPhone)){{ $maskedPhone }}@endif</div>
            <p style="margin-top:12px"><strong>{{ $customer->customer_nama }}</strong>
                @if(!empty($cabangLabel))<span class="muted"> · {{ $cabangLabel }}</span>@endif
            </p>
            <a href="{{ route('login') }}" class="btn block" style="margin-top:16px">Ke halaman masuk</a>
        @endif
    </div>
    <p class="muted" style="text-align:center;margin-top:16px">
        <a href="{{ route('register') }}">Daftar baru</a> · <a href="{{ route('login') }}">Masuk</a>
    </p>
@endsection
