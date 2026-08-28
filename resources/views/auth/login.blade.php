@extends('layouts.app')

@section('page_class', 'page--auth')
@section('title', 'Masuk — '.config('marketplace.name'))

@section('content')
    <div class="panel" style="max-width:420px;margin:24px auto">
        <h2 style="margin:0 0 8px;font-size:1.25rem">Masuk Member</h2>
        <p class="muted" style="margin:0 0 20px">Gunakan nomor kartu member Numart (contoh: NUBLJ00000123).</p>

        @if($errors->any())
            <div class="toast toast--err" style="margin-bottom:12px">{{ $errors->first() }}</div>
        @endif

        <form method="post" action="{{ route('login') }}">
            @csrf
            <div class="field">
                <label>Nomor kartu member</label>
                <input type="text" name="card_number" required autofocus autocomplete="off"
                       value="{{ old('card_number') }}" placeholder="NUBLJ00000000" style="text-transform:uppercase">
            </div>
            <button class="btn block" type="submit">Masuk</button>
        </form>
    </div>
@endsection
