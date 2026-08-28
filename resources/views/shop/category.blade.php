@extends('layouts.app')

@section('title', $category->kategori_nama.' — '.config('marketplace.name'))
@section('page_class', 'page--category')

@section('breadcrumb')
    @include('layouts.partials.breadcrumb', ['items' => [
        ['label' => 'Beranda', 'url' => route('shop.index')],
        ['label' => 'Kategori', 'url' => url('/kategori')],
        ['label' => $category->kategori_nama],
    ]])
@endsection

@section('header_search')
    <form class="search-pill" method="get" action="{{ url('/kategori/'.$category->kategori_id) }}">
        @foreach(($filters ?? null)?->queryParams('q') ?? [] as $key => $val)
            <input type="hidden" name="{{ $key }}" value="{{ $val }}">
        @endforeach
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3-3"/></svg>
        <input type="search" name="q" value="{{ $search }}" placeholder="Cari di {{ Str::limit($category->kategori_nama, 24) }}...">
        <button type="submit">Cari</button>
    </form>
@endsection

@section('content')
    <div class="catalog-layout">
        @include('shop.partials.product-filters', [
            'filters' => $filters ?? null,
            'formAction' => url('/kategori/'.$category->kategori_id),
            'search' => $search,
            'fixedKategoriId' => (int) $category->kategori_id,
        ])

        <div class="catalog-layout__main">
            <div class="section-head" style="margin-top:8px">
                <h2 style="margin:0">{{ $category->kategori_nama }}</h2>
                <span class="muted" style="font-size:0.75rem;font-weight:600">Harga {{ $tierLabel }}</span>
                @if(($filters ?? null)?->isActive(null, true))
                    <span class="catalog-filters__active-pill">Filter aktif</span>
                @endif
            </div>

            @if($products->isEmpty())
                <div class="panel" style="text-align:center;padding:32px 20px">
                    <p class="muted" style="margin:0">Belum ada produk di kategori ini.</p>
                </div>
            @else
                @include('shop.partials.product-grid', ['products' => $products])
                @include('shop.partials.pagination', ['paginator' => $products])
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('js/catalog-filters.js') }}" defer></script>
@endpush
