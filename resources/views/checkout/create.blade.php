@extends('layouts.app')

@section('title', 'Checkout')

@section('content')
    <div class="section-head">
        <h2 style="margin:0;font-size:1.25rem">Checkout</h2>
    </div>
    <div class="panel" style="margin-bottom:14px">
        <p class="muted" style="margin:0">Cabang: <strong>{{ $branchLabel }}</strong></p>
        <p class="muted" style="margin:6px 0 0">Minimal pembelian {{ $tierLabel }}: <strong>Rp {{ number_format($minOrder, 0, ',', '.') }}</strong></p>
    </div>

    @if($belowMin)
        <div class="toast toast--err" style="margin-bottom:14px">
            Subtotal belum mencapai minimal pembelian. Tambahkan produk terlebih dahulu.
        </div>
    @endif

    <div class="panel">
        @foreach($items as $item)
            <div class="cart-item">
                <span>{{ $item['barang_nama'] }} × {{ $item['qty'] }}</span>
                <span>Rp {{ number_format($item['price'] * $item['qty'], 0, ',', '.') }}</span>
            </div>
        @endforeach
        <div class="summary-row"><span>Subtotal</span><span>Rp {{ number_format($subtotal, 0, ',', '.') }}</span></div>
        @if($shipping > 0)
            <div class="summary-row"><span>Ongkir</span><span>Rp {{ number_format($shipping, 0, ',', '.') }}</span></div>
        @endif
        <div class="summary-row total"><span>Total</span><span>Rp {{ number_format($subtotal + $shipping, 0, ',', '.') }}</span></div>
    </div>

    <div class="panel" style="margin-top:14px">
        <form method="post" action="{{ route('checkout.store') }}" id="checkout-form">
            @csrf
            <div class="field">
                <label>Nama penerima</label>
                <input type="text" name="name" required value="{{ old('name', $user->name ?? '') }}">
            </div>
            <div class="field">
                <label>No. WhatsApp / HP</label>
                <input type="tel" name="phone" required value="{{ old('phone', $user->phone ?? '') }}">
            </div>
            <div class="field">
                <label>Alamat lengkap</label>
                <textarea name="address" rows="3" required>{{ old('address', $user->address ?? '') }}</textarea>
            </div>

            <p style="font-weight:700;margin:16px 0 8px">Metode pembayaran</p>
            <label class="panel" style="display:block;margin-bottom:8px;cursor:pointer">
                <input type="radio" name="payment_method" value="transfer" {{ old('payment_method', 'transfer') === 'transfer' ? 'checked' : '' }} required>
                <strong>Transfer</strong>
                <span class="muted" style="display:block;font-size:0.85rem">Scan QRIS cabang, upload bukti, kirim via WhatsApp</span>
            </label>
            <label class="panel" style="display:block;margin-bottom:16px;cursor:pointer;{{ !$canCod ? 'opacity:.55' : '' }}">
                <input type="radio" name="payment_method" value="cod" {{ !$canCod ? 'disabled' : '' }} {{ old('payment_method') === 'cod' ? 'checked' : '' }}>
                <strong>COD (bayar di tempat)</strong>
                <span class="muted" style="display:block;font-size:0.85rem">
                    @if($canCod)
                        Pesanan langsung dikirim ke WhatsApp cabang
                    @else
                        Hanya member terverifikasi. <a href="{{ route('member.verification.create') }}">Verifikasi akun</a>
                    @endif
                </span>
            </label>

            <button type="submit" class="btn block" {{ $belowMin ? 'disabled' : '' }}>Buat pesanan</button>
        </form>
    </div>
@endsection
