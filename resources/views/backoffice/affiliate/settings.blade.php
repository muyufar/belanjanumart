@extends('layouts.backoffice')

@section('title', 'Komisi margin')

@section('content')
    <h1 style="margin-top:0">Bagi hasil margin bersih Numart</h1>
    <p class="muted">Kader menerima <strong>% dari margin bersih</strong> (subtotal − HPP). Tarif naik dari min ke max seiring margin subtotal lebih sehat.</p>

    <div class="panel" style="max-width:480px">
        <form method="post" action="{{ route('backoffice.affiliate.settings.update') }}">
            @csrf
            <div class="field">
                <label>Minimum (% dari margin bersih)</label>
                <input type="number" step="0.01" name="margin_share_min" required value="{{ $settings['margin_share_min'] }}">
            </div>
            <div class="field">
                <label>Maximum (% dari margin bersih)</label>
                <input type="number" step="0.01" name="margin_share_max" required value="{{ $settings['margin_share_max'] }}">
            </div>
            <button type="submit" class="btn">Simpan</button>
        </form>
    </div>
@endsection
