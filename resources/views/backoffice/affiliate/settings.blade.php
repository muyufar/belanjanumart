@extends('layouts.backoffice')

@section('title', 'Komisi affiliate')

@section('content')
    <h1 style="margin-top:0">Komisi mitra kader</h1>
    <p class="muted">Persen komisi yang diterima kader dari setiap pesanan mitranya. Perubahan berlaku untuk pesanan yang dibayar setelah disimpan; komisi yang sudah tercatat tidak berubah.</p>

    <form method="post" action="{{ route('backoffice.affiliate.settings.update') }}">
        @csrf

        @if($errors->any())
            <div class="toast toast--err" style="margin-bottom:12px">{{ $errors->first() }}</div>
        @endif

        <div class="panel" style="max-width:520px;margin-bottom:16px">
            <h2 style="margin-top:0;font-size:1.05rem">Persen per jenis mitra</h2>
            <p class="muted" style="margin-top:0">Dipakai untuk pesanan dari NUMART ERP (HPP tidak tersedia).</p>
            <div class="field">
                <label>Mitra warung (%)</label>
                <input type="number" step="0.01" min="0" max="100" name="warung" required value="{{ old('warung', $settings['warung']) }}">
            </div>
            <div class="field">
                <label>Mitra prioritas (%)</label>
                <input type="number" step="0.01" min="0" max="100" name="prioritas" required value="{{ old('prioritas', $settings['prioritas']) }}">
            </div>
            <div class="field">
                <label>Member lewat kode referral (%)</label>
                <input type="number" step="0.01" min="0" max="100" name="referral" required value="{{ old('referral', $settings['referral']) }}">
            </div>
            <div class="field">
                <label>Dihitung dari</label>
                <select name="basis">
                    <option value="subtotal" @selected(old('basis', $settings['basis']) === 'subtotal')>Subtotal belanja (tanpa ongkir)</option>
                    <option value="grand_total" @selected(old('basis', $settings['basis']) === 'grand_total')>Total bayar (termasuk ongkir, setelah diskon)</option>
                </select>
            </div>
        </div>

        <div class="panel" style="max-width:520px;margin-bottom:16px">
            <h2 style="margin-top:0;font-size:1.05rem">Bagi hasil margin bersih</h2>
            <p class="muted" style="margin-top:0">Hanya untuk pesanan lama dari POS (punya HPP): kader menerima % dari margin bersih (subtotal − HPP), naik dari min ke max seiring margin lebih sehat.</p>
            <div class="field">
                <label>Minimum (% dari margin bersih)</label>
                <input type="number" step="0.01" min="0" max="100" name="margin_share_min" required value="{{ old('margin_share_min', $settings['margin_share_min']) }}">
            </div>
            <div class="field">
                <label>Maximum (% dari margin bersih)</label>
                <input type="number" step="0.01" min="0" max="100" name="margin_share_max" required value="{{ old('margin_share_max', $settings['margin_share_max']) }}">
            </div>
        </div>

        <button type="submit" class="btn">Simpan</button>
    </form>
@endsection
