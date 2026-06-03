@extends('layouts.app')

@section('title', 'Checkout')

@section('content')
    <div class="section-head">
        <h2 style="margin:0;font-size:1.25rem">Checkout</h2>
    </div>
    <div class="panel" style="margin-bottom:14px">
        <p class="muted" style="margin:0">Dikirim dari: <strong>{{ $preview['label'] ?? 'Gudang Nugrasir' }}</strong></p>
    </div>

    <div class="panel">
        @foreach($items as $item)
            <div class="cart-item">
                <span>{{ $item['barang_nama'] }} × {{ $item['qty'] }}</span>
                <span>Rp {{ number_format($item['price'] * $item['qty'], 0, ',', '.') }}</span>
            </div>
        @endforeach
        <div class="summary-row"><span>Subtotal</span><span>Rp {{ number_format($subtotal, 0, ',', '.') }}</span></div>
        <div class="summary-row"><span>Ongkir</span><span>Rp {{ number_format($shipping, 0, ',', '.') }}</span></div>
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
            <input type="hidden" name="lat" id="lat" value="{{ old('lat') }}">
            <input type="hidden" name="lng" id="lng" value="{{ old('lng') }}">
            <p class="muted" id="geo-hint" style="margin-bottom:12px">Izinkan lokasi untuk cabang terdekat.</p>
            <button type="submit" class="btn block">Bayar via BRI Virtual Account</button>
        </form>
    </div>
@endsection

@push('scripts')
<script>
if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(function (p) {
        document.getElementById('lat').value = p.coords.latitude;
        document.getElementById('lng').value = p.coords.longitude;
        var h = document.getElementById('geo-hint');
        if (h) h.textContent = 'Lokasi tercatat — cabang terdekat diprioritaskan.';
    });
}
</script>
@endpush
