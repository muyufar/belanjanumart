<header class="app-header">
    {{-- Mobile header --}}
    <div class="app-header__mobile">
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
            <div class="app-header__actions">
                <button type="button" class="theme-toggle" id="themeToggle" aria-label="Ganti tema terang/gelap">
                    <svg class="theme-toggle__sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="12" cy="12" r="4"/>
                        <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
                    </svg>
                    <svg class="theme-toggle__moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
                    </svg>
                </button>
                <a href="{{ auth()->check() ? route('profile.show') : route('login') }}" class="app-header__avatar" aria-label="Akun">
                    @auth
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    @else
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-6 8-6s8 2 8 6"/></svg>
                    @endauth
                </a>
            </div>
        </div>
        @hasSection('header_search')
            @yield('header_search')
        @endif
    </div>

    {{-- Desktop topbar --}}
    <div class="desktop-topbar">
        <div class="desktop-topbar__intro">
            @auth
                <span class="desktop-topbar__hello">Halo, {{ Str::limit(auth()->user()->name, 28) }}</span>
            @else
                <span class="desktop-topbar__hello">Selamat datang di NU Mart</span>
            @endauth
            @isset($tierLabel)
                <span class="tier-pill">{{ $tierLabel }}</span>
            @endisset
        </div>
        @hasSection('header_search')
            <div class="desktop-topbar__search">
                @yield('header_search')
            </div>
        @endif
    </div>
</header>
