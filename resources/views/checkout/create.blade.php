@extends('layouts.app')

@section('title', 'Checkout')
@section('page_class', 'page--checkout')

@section('breadcrumb')
    @include('layouts.partials.breadcrumb', ['items' => [
        ['label' => 'Beranda', 'url' => route('shop.index')],
        ['label' => 'Keranjang', 'url' => route('cart.index')],
        ['label' => 'Checkout'],
    ]])
@endsection

@section('content')
    <div class="section-head">
        <h2 style="margin:0;font-size:1.25rem">Checkout</h2>
    </div>

    @if($belowMin)
        <div class="toast toast--err" style="margin-bottom:14px">
            Subtotal belum mencapai minimal pembelian. Tambahkan produk terlebih dahulu.
        </div>
    @endif

    @if($shippingRequired)
        <div class="panel" style="margin-bottom:16px">
            <strong>Pengiriman Nugrosir</strong>
            <p>Isi alamat dan pilih layanan, lalu ajukan ongkir. Pembayaran dilakukan setelah rute, muatan, jadwal dan biaya dikonfirmasi.</p>
            @foreach($quotes as $offer)
                <div style="margin:8px 0">
                    <a href="{{ route('checkout.create', ['quote' => $offer->id]) }}">Penawaran #{{ $offer->id }}</a>
                    — {{ ['pending' => 'Menunggu pemeriksaan', 'approved' => 'Siap ditinjau', 'consumed' => 'Sudah dipakai', 'cancelled' => 'Dibatalkan', 'expired' => 'Berakhir'][$offer->status] ?? $offer->status }}
                    @if(in_array($offer->status, ['pending', 'approved']))
                        <form method="post" action="{{ route('checkout.shipping.cancel', $offer->id) }}" style="display:inline">@csrf <button type="submit" class="btn btn--ghost">Batalkan penawaran</button></form>
                    @endif
                </div>
            @endforeach
            @if($quoteReady)
                <p><strong>{{ ['direct' => 'Kirim langsung', 'scheduled' => 'Hemat terjadwal', 'manual' => 'Pengiriman khusus'][$quoteSnapshot['route']['service']] }}</strong><br>
                Estimasi diterima {{ \Carbon\Carbon::parse($quoteSnapshot['route']['start'], 'UTC')->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}–{{ \Carbon\Carbon::parse($quoteSnapshot['route']['end'], 'UTC')->timezone('Asia/Jakarta')->format('H:i') }} WIB.<br>
                Setujui sebelum {{ \Carbon\Carbon::parse($quote->expires_at, 'UTC')->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB. Perubahan keranjang atau alamat memerlukan penawaran baru.</p>
            @endif
        </div>
    @endif

    <div class="checkout-layout">
        <div class="checkout-layout__form">
            <div class="panel" style="margin-bottom:14px">
                <p class="muted" style="margin:0">Cabang: <strong>{{ $branchLabel }}</strong></p>
                <p class="muted" style="margin:6px 0 0">Minimal pembelian {{ $tierLabel }}: <strong>Rp {{ number_format($minOrder, 0, ',', '.') }}</strong></p>
            </div>

            <div class="panel">
                <form method="post" action="{{ route('checkout.store') }}" id="checkout-form">
                    @csrf
                    <div class="field">
                        <label>Nama penerima</label>
                        <input type="text" name="name" required value="{{ old('name', $quoteData['name'] ?? $user->name ?? '') }}">
                    </div>
                    <div class="field">
                        <label>No. WhatsApp / HP</label>
                        <input type="tel" name="phone" required value="{{ old('phone', $quoteData['phone'] ?? $user->phone ?? '') }}">
                    </div>
                    <div class="field">
                        <label>Alamat lengkap</label>
                        <textarea name="address" rows="3" required>{{ old('address', $quoteData['address'] ?? $user->address ?? '') }}</textarea>
                    </div>

                    <p style="font-weight:700;margin:16px 0 8px">Metode pembayaran</p>
                    <label class="panel payment-option" style="display:block;margin-bottom:8px;cursor:pointer">
                        <input type="radio" name="payment_method" value="transfer" {{ old('payment_method', $quoteData['payment_method'] ?? 'transfer') === 'transfer' ? 'checked' : '' }} required>
                        <strong>Transfer</strong>
                        <span class="muted" style="display:block;font-size:0.85rem">Scan QRIS cabang, upload bukti, kirim via WhatsApp</span>
                    </label>
                    <label class="panel payment-option" style="display:block;margin-bottom:16px;cursor:pointer;{{ !$canCod ? 'opacity:.55' : '' }}">
                        <input type="radio" name="payment_method" value="cod" {{ !$canCod ? 'disabled' : '' }} {{ old('payment_method', $quoteData['payment_method'] ?? '') === 'cod' ? 'checked' : '' }}>
                        <strong>COD (bayar di tempat)</strong>
                        <span class="muted" style="display:block;font-size:0.85rem">
                            @if($canCod)
                                Pesanan langsung dikirim ke WhatsApp cabang
                            @else
                                Hanya member terverifikasi. <a href="{{ route('member.verification.create') }}">Verifikasi akun</a>
                            @endif
                        </span>
                    </label>

                    @if($shippingRequired)
                        <input type="hidden" name="shipping_quote_id" value="{{ $quoteReady ? $quote->id : '' }}">
                        <div class="field"><label for="shipping_service">Layanan pengiriman</label>
                        <select id="shipping_service" name="shipping_service" required>
                            @foreach(['direct' => 'Kirim langsung', 'scheduled' => 'Hemat terjadwal (11–13 / 16–18 WIB)', 'manual' => 'Pengiriman khusus / barang berat'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('shipping_service', $quoteData['service'] ?? 'direct') === $value)>{{ $label }}</option>
                            @endforeach
                        </select></div>
                        <button type="submit" formaction="{{ route('checkout.shipping') }}" class="btn block" {{ $belowMin ? 'disabled' : '' }}>Ajukan ongkir</button>
                    @endif
                    <button type="submit" class="btn block checkout-layout__submit-mobile" {{ $belowMin || ($shippingRequired && !$quoteReady) ? 'disabled' : '' }}>{{ $shippingRequired ? 'Setujui total dan buat pesanan' : 'Buat pesanan' }}</button>
                </form>
            </div>
        </div>

        <aside class="checkout-layout__summary">
            <div class="panel checkout-summary">
                <h3 class="checkout-summary__title">Ringkasan pesanan</h3>
                @foreach($items as $item)
                    <div class="cart-item">
                        <span>{{ $item['barang_nama'] }} × {{ $item['qty'] }}</span>
                        <span>Rp {{ number_format($item['price'] * $item['qty'], 0, ',', '.') }}</span>
                    </div>
                @endforeach
                <div class="summary-row"><span>Subtotal</span><span>Rp {{ number_format($subtotal, 0, ',', '.') }}</span></div>
                @if($shippingRequired && !$quoteReady)
                    <p>Ongkir dan total pembayaran menunggu penawaran admin.</p>
                @else
                @if($shippingRequired && $quoteReady)
                    <div class="summary-row"><span>Tarif pengiriman</span><span>Rp {{ number_format($quoteSnapshot['money']['fare'], 0, ',', '.') }}</span></div>
                    <div class="summary-row"><span>Subsidi toko</span><span>−Rp {{ number_format($quoteSnapshot['money']['subsidy'], 0, ',', '.') }}</span></div>
                @endif
                @if($shipping > 0)
                    <div class="summary-row"><span>Ongkir</span><span>Rp {{ number_format($shipping, 0, ',', '.') }}</span></div>
                @endif
                <div class="summary-row total"><span>Total</span><span>Rp {{ number_format($subtotal + $shipping, 0, ',', '.') }}</span></div>
                @endif
                <button type="submit" form="checkout-form" class="btn block checkout-layout__submit-desktop" style="margin-top:16px" {{ $belowMin || ($shippingRequired && !$quoteReady) ? 'disabled' : '' }}>{{ $shippingRequired ? 'Setujui total dan buat pesanan' : 'Buat pesanan' }}</button>
            </div>
        </aside>
    </div>
@endsection
