@extends('layouts.app')

@section('title', 'Kategori — '.config('marketplace.name'))
@section('page_class', 'page--categories')

@section('content')
    @php $categoryIcons = app(\App\Services\CategoryIconService::class); @endphp

    <div class="section-head">
        <h2 style="margin:0">Semua Kategori</h2>
    </div>

    <div class="cat-grid">
        @include('shop.partials.category-link', [
            'href' => route('shop.index'),
            'label' => 'Semua',
            'visual' => $categoryIcons->forAll(),
        ])
        @include('shop.partials.category-link', [
            'href' => route('shop.index', ['tipe' => 'terbaru']),
            'label' => 'Terbaru',
            'visual' => $categoryIcons->forProductType('terbaru'),
        ])
        @include('shop.partials.category-link', [
            'href' => route('shop.index', ['tipe' => 'terlaris']),
            'label' => 'Terlaris',
            'visual' => $categoryIcons->forProductType('terlaris'),
        ])
        @foreach($categories as $kat)
        @include('shop.partials.category-link', [
            'href' => url('/kategori/'.$kat->kategori_id),
            'label' => $kat->kategori_nama,
            'visual' => $categoryIcons->forCategory($kat),
        ])
        @endforeach
    </div>

    @if($categories->isEmpty())
        <div class="panel" style="text-align:center;padding:32px 20px">
            <p class="muted" style="margin:0">Belum ada kategori.</p>
        </div>
    @endif
@endsection
