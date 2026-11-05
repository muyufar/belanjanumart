<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Backoffice') — {{ config('marketplace.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/marketplace.css') }}">
    <link rel="stylesheet" href="{{ asset('css/backoffice.css') }}">
</head>
<body class="app-body backoffice-body">
<div class="backoffice-layout">
    <aside class="backoffice-sidebar">
        <div class="backoffice-sidebar__brand">Backoffice NU Mart</div>
        <nav class="backoffice-nav">
            <a href="{{ route('backoffice.dashboard') }}">Dashboard</a>
            <a href="{{ route('backoffice.affiliate.kaders') }}">Mitra kader</a>
            <a href="{{ route('backoffice.affiliate.settings') }}">Komisi margin</a>
            <a href="{{ route('backoffice.affiliate.commissions') }}">Pembayaran komisi</a>
            <a href="{{ route('shop.index') }}">← Toko online</a>
        </nav>
        <form method="post" action="{{ route('backoffice.logout') }}" class="backoffice-logout">
            @csrf
            <button type="submit" class="btn secondary block">Keluar</button>
        </form>
    </aside>
    <main class="backoffice-main">
        @if(session('success'))
            <div class="toast toast--ok">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="toast toast--err">{{ session('error') }}</div>
        @endif
        @yield('content')
    </main>
</div>
</body>
</html>
