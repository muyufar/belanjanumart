@extends('layouts.app')

@section('title', $product->barang_nama)
@section('page_class', 'page--detail')

@section('content')
    <a href="{{ $kategoriId ? route('shop.index', ['kategori' => $kategoriId]) : route('shop.index') }}" class="detail-back">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
        Kembali
    </a>

    <div class="product-detail-media">
        @include('shop.partials.product-placeholder', [
            'product' => $product,
            'hidden' => !empty($product->image_url),
            'size' => 'detail',
        ])
        @if($product->image_url)
            <img
                src="{{ $product->image_url }}"
                alt="{{ $product->barang_nama }}"
                class="product-detail-img"
                onerror="this.style.display='none';var p=this.previousElementSibling;if(p)p.classList.remove('product-placeholder--hidden');"
            >
        @endif
    </div>

    <h1 class="detail-title">{{ $product->barang_nama }}</h1>
    @if(!empty($product->has_discount) && $product->price_original)
        <p class="price price--sale" style="font-size:1.5rem;margin:0">Rp {{ number_format($product->price, 0, ',', '.') }}</p>
        <p class="price-old">Rp {{ number_format($product->price_original, 0, ',', '.') }} @if($product->discount_label)({{ $product->discount_label }})@endif</p>
    @else
        <p class="price" style="font-size:1.5rem;margin:0 0 8px">Rp {{ number_format($product->price, 0, ',', '.') }}</p>
    @endif
    <p class="muted">Harga {{ $tierLabel }} · {{ $product->barang_kode }}</p>

    <div class="sticky-cart-bar">
        <form method="post" action="{{ route('cart.store') }}" class="add-cart-form" style="flex:1;margin:0">
            @csrf
            <input type="hidden" name="barang_id" value="{{ $product->barang_id }}">
            <input type="number" name="qty" value="1" min="1" max="99" class="qty-input" aria-label="Jumlah">
            <button type="submit" class="btn" style="flex:1">Tambah keranjang</button>
        </form>
    </div>

    @if($relatedProducts && $relatedProducts->total() > 0)
        <section class="related-section">
            <div class="section-head">
                <h2 class="related-title" style="margin:0">Produk serupa</h2>
            </div>
            @include('shop.partials.product-grid', ['products' => $relatedProducts])
            @include('shop.partials.pagination', ['paginator' => $relatedProducts])
        </section>
    @endif
@endsection
