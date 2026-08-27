@extends('layouts.app')

@section('title', $category->kategori_nama.' — '.config('marketplace.name'))
@section('page_class', 'page--category')

@section('header_search')
    <form class="search-pill" method="get" action="{{ route('shop.category', $category->kategori_id) }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3-3"/></svg>
        <input type="search" name="q" value="{{ $search }}" placeholder="Cari di {{ Str::limit($category->kategori_nama, 24) }}...">
        <button type="submit">Cari</button>
    </form>
@endsection

@section('content')
    <a href="{{ route('shop.categories') }}" class="detail-back">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
        Kategori
    </a>

    <div class="section-head" style="margin-top:8px">
        <h2 style="margin:0">{{ $category->kategori_nama }}</h2>
        <span class="muted" style="font-size:0.75rem;font-weight:600">Harga {{ $tierLabel }}</span>
    </div>

    @if($products->isEmpty())
        <div class="panel" style="text-align:center;padding:32px 20px">
            <p class="muted" style="margin:0">Belum ada produk di kategori ini.</p>
        </div>
    @else
        @include('shop.partials.product-grid', ['products' => $products])
        @include('shop.partials.pagination', ['paginator' => $products])
    @endif
@endsection
