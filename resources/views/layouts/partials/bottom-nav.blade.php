@php
    $route = request()->route()?->getName();
    $cartN = (int) ($cartCount ?? 0);
@endphp
<nav class="bottom-nav" aria-label="Navigasi utama">
    <a href="{{ route('shop.index') }}" class="bottom-nav__item {{ $route === 'shop.index' ? 'is-active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 10.5L12 4l9 6.5V20a1 1 0 01-1 1h-5v-6H9v6H4a1 1 0 01-1-1v-9.5z"/></svg>
        <span>Beranda</span>
    </a>
    <a href="{{ route('shop.categories') }}" class="bottom-nav__item {{ in_array($route, ['shop.categories', 'shop.category'], true) ? 'is-active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
        <span>Kategori</span>
    </a>
    <a href="{{ route('cart.index') }}" class="bottom-nav__item {{ $route === 'cart.index' ? 'is-active' : '' }}">
        <span class="bottom-nav__icon-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6h15l-1.5 9H7.5L6 6z"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/><path d="M6 6L5 3H2"/></svg>
            @if($cartN > 0)<em class="bottom-nav__badge">{{ $cartN > 99 ? '99+' : $cartN }}</em>@endif
        </span>
        <span>Keranjang</span>
    </a>
    @auth
        <a href="{{ route('profile.show') }}" class="bottom-nav__item {{ $route === 'profile.show' ? 'is-active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-6 8-6s8 2 8 6"/></svg>
            <span>Profil</span>
        </a>
    @else
        <a href="{{ route('login') }}" class="bottom-nav__item {{ in_array($route, ['login', 'register', 'activate', 'activate.lookup', 'activate.choose', 'password.forgot', 'password.forgot.send', 'password.forgot.choose'], true) ? 'is-active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-6 8-6s8 2 8 6"/></svg>
            <span>Masuk</span>
        </a>
    @endauth
</nav>
