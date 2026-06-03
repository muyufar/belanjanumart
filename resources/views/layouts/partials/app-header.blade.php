<header class="app-header">
    <div class="app-header__top">
        <div class="app-header__greet">
            <span class="app-header__hello">Halo{{ auth()->check() ? ',' : '!' }}</span>
            @auth
                <strong class="app-header__name">{{ Str::limit(auth()->user()->name, 18) }}</strong>
            @else
                <strong class="app-header__name">Selamat belanja</strong>
            @endauth
            @isset($tierLabel)
                <span class="tier-pill">{{ $tierLabel }}</span>
            @endisset
        </div>
        <a href="{{ auth()->check() ? route('profile.show') : route('login') }}" class="app-header__avatar" aria-label="Akun">
            @auth
                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
            @else
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-6 8-6s8 2 8 6"/></svg>
            @endauth
        </a>
    </div>
    @hasSection('header_search')
        @yield('header_search')
    @endif
</header>
