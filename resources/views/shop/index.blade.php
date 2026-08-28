@extends('layouts.app')

@section('title', 'Belanja — '.config('marketplace.name'))

@section('breadcrumb')
    @if($search || $kategoriId || ($tipe ?? null))
        @php
            $crumbs = [['label' => 'Beranda', 'url' => route('shop.index')]];
            if ($tipe ?? null) {
                $crumbs[] = ['label' => ($tipe === 'terlaris' ? 'Terlaris' : 'Terbaru')];
            } elseif ($search) {
                $crumbs[] = ['label' => 'Cari: '.$search];
            }
        @endphp
        @include('layouts.partials.breadcrumb', ['items' => $crumbs])
    @endif
@endsection

@section('header_search')
    <form class="search-pill" method="get" action="{{ route('shop.index') }}">
        @if($kategoriId)
            <input type="hidden" name="kategori" value="{{ $kategoriId }}">
        @endif
        @if($tipe ?? null)
            <input type="hidden" name="tipe" value="{{ $tipe }}">
        @endif
        @foreach(($filters ?? null)?->queryParams('q') ?? [] as $key => $val)
            <input type="hidden" name="{{ $key }}" value="{{ $val }}">
        @endforeach
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3-3"/></svg>
        <input type="search" name="q" value="{{ $search }}" placeholder="Cari produk, merek...">
        <button type="submit">Cari</button>
    </form>
@endsection

@section('content')
    @php $categoryIcons = app(\App\Services\CategoryIconService::class); @endphp

    @if(!$search && !$kategoriId && !($tipe ?? null))
        @include('shop.partials.hero-carousel', [
            'tierLabel' => $tierLabel,
            'branchLabel' => $branchLabel ?? 'Cabang member',
            'minOrder' => $minOrder ?? 0,
            'discounted' => $discounted ?? collect(),
        ])
    @endif

    {{-- 1. Icon kategori --}}
    <div id="kategori" class="section-head">
        <h2>Kategori</h2>
    </div>
    <div class="cat-scroll">
        <a href="{{ route('shop.index', request()->only('q')) }}"
           class="cat-item {{ !$kategoriId && !($tipe ?? null) ? 'is-active' : '' }}">
            @include('shop.partials.category-icon', ['visual' => $categoryIcons->forAll()])
            <span>Semua</span>
        </a>
        <a href="{{ route('shop.index', ['q' => $search, 'tipe' => 'terbaru']) }}"
           class="cat-item {{ ($tipe ?? null) === 'terbaru' ? 'is-active' : '' }}">
            @include('shop.partials.category-icon', ['visual' => $categoryIcons->forProductType('terbaru')])
            <span>Terbaru</span>
        </a>
        <a href="{{ route('shop.index', ['q' => $search, 'tipe' => 'terlaris']) }}"
           class="cat-item {{ ($tipe ?? null) === 'terlaris' ? 'is-active' : '' }}">
            @include('shop.partials.category-icon', ['visual' => $categoryIcons->forProductType('terlaris')])
            <span>Terlaris</span>
        </a>
        @foreach($categories->take(16) as $kat)
            <a href="{{ url('/kategori/'.$kat->kategori_id) }}"
               class="cat-item {{ $kategoriId == $kat->kategori_id ? 'is-active' : '' }}">
                @include('shop.partials.category-icon', ['visual' => $categoryIcons->forCategory($kat)])
                <span>{{ Str::limit($kat->kategori_nama, 11) }}</span>
            </a>
        @endforeach
    </div>

    @if($showHomeSections ?? false)
        {{-- 2. Flash sale --}}
        @if(($discounted ?? collect())->isNotEmpty())
            <section class="home-section" id="flash-sale">
                <div class="section-head">
                    <h2>Flash sale</h2>
                    <span class="muted" style="font-size:0.8rem;font-weight:600;color:var(--accent)">Diskon aktif</span>
                </div>
                @include('shop.partials.product-grid', ['products' => $discounted])
            </section>
        @endif

        {{-- 3. Produk Terbaru --}}
        @if(($latestProducts ?? collect())->isNotEmpty())
            <section class="home-section">
                <div class="section-head">
                    <h2>Produk Terbaru</h2>
                    <a href="{{ route('shop.index', ['tipe' => 'terbaru']) }}">Lihat semua</a>
                </div>
                @include('shop.partials.product-grid', ['products' => $latestProducts])
            </section>
        @endif

        {{-- 4. Produk Terlaris --}}
        @if(($bestSellers ?? collect())->isNotEmpty())
            <section class="home-section">
                <div class="section-head">
                    <h2>Produk Terlaris</h2>
                    <a href="{{ route('shop.index', ['tipe' => 'terlaris']) }}">Lihat semua</a>
                </div>
                @include('shop.partials.product-grid', ['products' => $bestSellers])
            </section>
        @endif
    @endif

    {{-- 5. Semua produk (atau filter aktif) --}}
    @php
        $listTitle = match ($tipe ?? null) {
            'terbaru' => 'Produk Terbaru',
            'terlaris' => 'Produk Terlaris',
            default => ($search || $kategoriId) ? 'Hasil pencarian' : 'Semua Produk',
        };
    @endphp

    <div class="catalog-layout">
        @include('shop.partials.product-filters', [
            'filters' => $filters ?? null,
            'formAction' => route('shop.index'),
            'search' => $search,
            'tipe' => $tipe ?? null,
            'showCategory' => !($tipe ?? null),
            'categories' => $categories,
        ])

        <div class="catalog-layout__main">
            <div class="section-head">
                <h2>{{ $listTitle }}</h2>
                @if(($tipe ?? null) === 'terlaris')
                    <span class="muted" style="font-size:0.75rem;font-weight:600">{{ $bestSellersDays }} hari terakhir</span>
                @endif
                @if($filters->isActive($tipe ?? null))
                    <span class="catalog-filters__active-pill">Filter aktif</span>
                @endif
                @guest
                    @if(!($tipe ?? null) && !($search || $kategoriId))
                        <a href="{{ route('register') }}">Daftar</a>
                    @endif
                @endguest
            </div>

            @if($products->isEmpty())
                <div class="panel" style="text-align:center;padding:32px 20px">
                    <p class="muted" style="margin:0">Produk tidak ditemukan.</p>
                </div>
            @else
                @include('shop.partials.product-grid', ['products' => $products])
                @include('shop.partials.pagination', ['paginator' => $products])
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('js/hero-carousel.js') }}" defer></script>
<script src="{{ asset('js/catalog-filters.js') }}" defer></script>
@endpush
