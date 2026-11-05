<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Backoffice — {{ config('marketplace.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/marketplace.css') }}">
</head>
<body class="app-body">
    <div class="panel" style="max-width:400px;margin:48px auto;padding:24px">
        <h1 style="margin:0 0 8px;font-size:1.25rem">Backoffice Belanja Online</h1>
        <p class="muted" style="margin:0 0 20px">Login staff untuk kelola mitra kader &amp; komisi affiliate.</p>

        @if($errors->any())
            <div class="toast toast--err" style="margin-bottom:12px">{{ $errors->first() }}</div>
        @endif

        <form method="post" action="{{ route('backoffice.login.store') }}">
            @csrf
            <div class="field">
                <label>Email</label>
                <input type="email" name="email" required value="{{ old('email') }}" autocomplete="username">
            </div>
            <div class="field">
                <label>Password</label>
                <input type="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn block">Masuk backoffice</button>
        </form>
        <p style="margin-top:16px;text-align:center"><a href="{{ route('shop.index') }}">Kembali ke toko</a></p>
    </div>
</body>
</html>
