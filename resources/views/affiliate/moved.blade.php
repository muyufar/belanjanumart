@extends('layouts.app')

@section('title', 'Mitra Kader NU')
@section('page_class', 'page--affiliate')

@section('breadcrumb')
    @include('layouts.partials.breadcrumb', ['items' => [
        ['label' => 'Beranda', 'url' => route('shop.index')],
        ['label' => 'Mitra Kader'],
    ]])
@endsection

@section('content')
    <div class="section-head">
        <h2 style="margin:0;font-size:1.25rem">Panel Mitra Kader</h2>
        @if(auth()->user()?->affiliate_code)
            <span class="tier-pill">Kode {{ auth()->user()->affiliate_code }}</span>
        @endif
    </div>
    @include('partials.erp-kader-moved', ['audience' => 'kader'])
    <p class="muted">Anda tetap bisa belanja di toko ini seperti biasa. Belanja mitra binaan — online maupun langsung di toko — tetap memberi komisi ke Anda.</p>
@endsection
