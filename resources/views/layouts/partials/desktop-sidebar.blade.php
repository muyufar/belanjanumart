@php
    $route = request()->route()?->getName();
    $cartN = (int) ($cartCount ?? 0);
    $navClass = fn (bool $active) => 'desktop-sidebar__link'.($active ? ' is-active' : '');
@endphp
<aside class="desktop-sidebar" aria-label="Navigasi utama">
    <a href="{{ route('shop.index') }}" class="desktop-sidebar__brand">
        <img src="{{ asset('images/numart-logo.jpg') }}" alt="" class="desktop-sidebar__logo" width="40" height="40">
        <span class="desktop-sidebar__brand-text">
            <strong>NU Mart</strong>
            <small>Belanja sambil Berkhidmat</small>
        </span>
    </a>

    <form class="desktop-sidebar__search" method="get" action="{{ route('shop.index') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3-3"/></svg>
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari produk, merek...">
    </form>

    <nav class="desktop-sidebar__nav">
        <a href="{{ route('shop.index') }}" class="{{ $navClass($route === 'shop.index') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 10.5L12 4l9 6.5V20a1 1 0 01-1 1h-5v-6H9v6H4a1 1 0 01-1-1v-9.5z"/></svg>
            <span>Beranda</span>
        </a>
        <a href="{{ url('/kategori') }}" class="{{ $navClass(request()->is('kategori', 'kategori/*')) }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
            <span>Kategori</span>
        </a>
        <a href="{{ route('shop.index', ['tipe' => 'terbaru']) }}" class="{{ $navClass(($route === 'shop.index') && request('tipe') === 'terbaru') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3l1.5 4.5L18 9l-4.5 1.5L12 15l-1.5-4.5L6 9l4.5-1.5L12 3z"/><path d="M5 19l1 3 1-3 3-1-3-1-1-3-1 3-3 1 3 1z"/></svg>
            <span>Terbaru</span>
        </a>
        <a href="{{ route('shop.index', ['tipe' => 'terlaris']) }}" class="{{ $navClass(($route === 'shop.index') && request('tipe') === 'terlaris') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3c1 3 4 4 4 8a4 4 0 11-8 0c0-4 3-5 4-8z"/></svg>
            <span>Terlaris</span>
        </a>
        <a href="{{ route('cart.index') }}" class="{{ $navClass($route === 'cart.index') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6h15l-1.5 9H7.5L6 6z"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/><path d="M6 6L5 3H2"/></svg>
            <span>Keranjang</span>
            @if($cartN > 0)<em class="desktop-sidebar__badge">{{ $cartN > 99 ? '99+' : $cartN }}</em>@endif
        </a>
    </nav>

    <div class="desktop-sidebar__footer">
        @isset($tierLabel)
            <span class="tier-pill desktop-sidebar__tier">{{ $tierLabel }}</span>
        @endisset
        <div class="desktop-sidebar__footer-actions">
            <button type="button" class="theme-toggle" id="themeToggleDesktop" aria-label="Ganti tema terang/gelap">
                <svg class="theme-toggle__sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
                </svg>
                <svg class="theme-toggle__moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
                </svg>
            </button>
            <a href="{{ auth()->check() ? route('profile.show') : route('login') }}" class="desktop-sidebar__account">
                @auth
                    <span class="desktop-sidebar__account-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                    <span class="desktop-sidebar__account-name">{{ Str::limit(auth()->user()->name, 20) }}</span>
                @else
                    <span class="desktop-sidebar__account-avatar">?</span>
                    <span class="desktop-sidebar__account-name">Masuk / Daftar</span>
                @endauth
            </a>
        </div>
    </div>
</aside>
