@extends('layouts.app')

@section('title', 'Daftar mitra')
@section('page_class', 'page--affiliate')

@section('content')
    <div class="section-head"><h2 style="margin:0">Daftar mitra warung / prioritas</h2></div>

    <div class="panel">
        <p class="muted" style="margin-top:0">Sistem akan membuat member POS (jika belum ada) dan menghubungkannya ke kode kader Anda.</p>
        <form method="post" action="{{ route('affiliate.referrals.store') }}">
            @csrf
            <div class="field">
                <label>Jenis mitra</label>
                <select name="partner_kind" required>
                    <option value="warung" @selected(old('partner_kind') === 'warung')>Mitra warung (retail)</option>
                    <option value="prioritas" @selected(old('partner_kind') === 'prioritas')>Mitra prioritas (grosir)</option>
                </select>
            </div>
            <div class="field">
                <label>Nama pemilik / penerima</label>
                <input type="text" name="name" required value="{{ old('name') }}">
            </div>
            <div class="field">
                <label>No. WhatsApp / HP</label>
                <input type="tel" name="phone" required value="{{ old('phone') }}">
            </div>
            <div class="field">
                <label>Nama warung / usaha (opsional)</label>
                <input type="text" name="warung_name" value="{{ old('warung_name') }}">
            </div>
            <div class="field">
                <label>Alamat lengkap</label>
                <textarea name="address" rows="3">{{ old('address') }}</textarea>
            </div>
            <button type="submit" class="btn block">Simpan &amp; daftarkan</button>
        </form>
    </div>

    <p style="margin-top:12px"><a href="{{ route('affiliate.referrals') }}">← Batal</a></p>
@endsection
