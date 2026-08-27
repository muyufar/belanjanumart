@extends('layouts.app')

@section('hide_header')
@endsection

@section('title', 'Katalog sementara tidak tersedia')

@section('content')
    <div class="panel" style="max-width:420px;margin:48px auto;text-align:center">
        <h1 style="font-size:1.2rem;margin:0 0 12px">Katalog sementara tidak tersedia</h1>
        <p class="muted" style="margin:0 0 16px">
            Sistem belum bisa terhubung ke database produk Numart. Tim teknis sedang memperbaiki koneksi server.
        </p>
        <form method="post" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn secondary block">Keluar</button>
        </form>
    </div>
@endsection
