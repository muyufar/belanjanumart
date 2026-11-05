@extends('layouts.app')

@section('title', 'Pengaturan komisi affiliate')

@section('content')
    <h1>Persentase komisi mitra kader</h1>

    <div class="panel" style="max-width:480px">
        <form method="post" action="{{ route('admin.affiliate.settings.update') }}">
            @csrf
            <div class="field">
                <label>Mitra warung (%)</label>
                <input type="number" name="warung" step="0.01" min="0" max="100" required value="{{ $settings['warung'] }}">
            </div>
            <div class="field">
                <label>Mitra prioritas (%)</label>
                <input type="number" name="prioritas" step="0.01" min="0" max="100" required value="{{ $settings['prioritas'] }}">
            </div>
            <div class="field">
                <label>Dihitung dari</label>
                <select name="basis">
                    <option value="subtotal" @selected($settings['basis'] === 'subtotal')>Subtotal barang</option>
                    <option value="grand_total" @selected($settings['basis'] === 'grand_total')>Total bayar (termasuk ongkir)</option>
                </select>
            </div>
            <button type="submit" class="btn">Simpan</button>
        </form>
    </div>

    <p style="margin-top:16px"><a href="{{ route('admin.affiliate.commissions') }}">Lihat komisi →</a></p>
@endsection
