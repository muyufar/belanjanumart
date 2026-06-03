<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#ffffff">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>@yield('title', config('marketplace.name'))</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/marketplace.css') }}">
</head>
<body class="app-body">
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
        @yield('content')
    </main>

    @include('layouts.partials.bottom-nav')
</div>
@stack('scripts')
</body>
</html>
