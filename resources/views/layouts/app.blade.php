<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1f5c38">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>@yield('title', config('marketplace.name'))</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('icon-512.png') }}">
    <script>
        (function () {
            try {
                var t = localStorage.getItem('belanja-theme');
                var dark = t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
                var m = document.querySelector('meta[name="theme-color"]');
                if (m) m.setAttribute('content', dark ? '#1a1f2e' : '#1f5c38');
            } catch (e) {}
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/marketplace.css') }}">
    <link rel="stylesheet" href="{{ asset('css/desktop.css') }}">
</head>
<body class="app-body">
<div class="app-layout">
    @include('layouts.partials.desktop-sidebar')

    <div class="app-shell">
        @hasSection('hide_header')
        @else
            @include('layouts.partials.app-header')
        @endif

        @if(session('success'))
            <div class="toast toast--ok" role="status">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="toast toast--err" role="alert">{{ session('error') }}</div>
        @endif

        <main class="page @yield('page_class')">
            <div class="page__inner">
                @yield('content')
            </div>
        </main>

        @include('layouts.partials.bottom-nav')
    </div>
</div>
<script src="{{ asset('js/theme.js') }}" defer></script>
@stack('scripts')
</body>
</html>
