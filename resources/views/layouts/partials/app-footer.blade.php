<footer class="app-footer">
    <div class="app-footer__inner">
        <div class="app-footer__brand">
            <img src="{{ asset('images/numart-logo.jpg') }}" alt="" class="app-footer__logo" width="48" height="48">
            <div>
                <strong>NU Mart</strong>
                <p>Belanja sambil Berkhidmat — marketplace resmi member Numart.</p>
            </div>
        </div>
        <nav class="app-footer__nav" aria-label="Footer">
            <a href="{{ route('shop.index') }}">Beranda</a>
            <a href="{{ url('/kategori') }}">Kategori</a>
            <a href="{{ route('shop.index', ['tipe' => 'terlaris']) }}">Terlaris</a>
            <a href="{{ route('cart.index') }}">Keranjang</a>
            @auth
                <a href="{{ route('profile.show') }}">Profil</a>
            @else
                <a href="{{ route('login') }}">Masuk</a>
            @endauth
        </nav>
        <div class="app-footer__meta">
            <span class="tier-pill">{{ $tierLabel ?? 'Member' }}</span>
            <p>Harga & stok mengikuti cabang fulfillment Anda.</p>
        </div>
    </div>
    <div class="app-footer__copy">
        © {{ date('Y') }} {{ config('marketplace.name') }}
    </div>
</footer>
