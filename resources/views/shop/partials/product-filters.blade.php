@php
    $filters = $filters ?? new \App\Services\CatalogProductFilters;
    $formAction = $formAction ?? url()->current();
    $search = $search ?? null;
    $tipe = $tipe ?? null;
    $showCategory = $showCategory ?? false;
    $categories = $categories ?? collect();
    $fixedKategoriId = $fixedKategoriId ?? null;
@endphp
<aside class="catalog-filters" aria-label="Filter produk">
    <form class="catalog-filters__form" method="get" action="{{ $formAction }}" data-catalog-filters>
        @if($search)
            <input type="hidden" name="q" value="{{ $search }}">
        @endif
        @if($tipe)
            <input type="hidden" name="tipe" value="{{ $tipe }}">
        @endif

        <div class="catalog-filters__head">
            <h3 class="catalog-filters__title">Filter</h3>
            @if($filters->isActive($tipe))
                <a href="{{ $formAction }}?{{ http_build_query(array_filter(['q' => $search, 'tipe' => $tipe])) }}" class="catalog-filters__reset">Reset</a>
            @endif
        </div>

        @if($showCategory && $categories->isNotEmpty())
            <div class="catalog-filters__field">
                <label for="filterKategori">Kategori</label>
                <select name="kategori" id="filterKategori">
                    <option value="">Semua kategori</option>
                    @foreach($categories as $kat)
                        <option value="{{ $kat->kategori_id }}" @selected($filters->kategoriId == $kat->kategori_id)>
                            {{ $kat->kategori_nama }}
                        </option>
                    @endforeach
                </select>
            </div>
        @elseif($fixedKategoriId)
            <input type="hidden" name="kategori" value="{{ $fixedKategoriId }}">
        @endif

        <div class="catalog-filters__field">
            <label for="filterSort">Urutkan</label>
            <select name="sort" id="filterSort">
                <option value="nama_asc" @selected($filters->sort === 'nama_asc')>Nama A–Z</option>
                <option value="nama_desc" @selected($filters->sort === 'nama_desc')>Nama Z–A</option>
                <option value="harga_asc" @selected($filters->sort === 'harga_asc')>Harga terendah</option>
                <option value="harga_desc" @selected($filters->sort === 'harga_desc')>Harga tertinggi</option>
                @if($tipe !== 'terbaru')
                    <option value="terbaru" @selected($filters->sort === 'terbaru')>Terbaru</option>
                @endif
                @if($tipe !== 'terlaris')
                    <option value="terlaris" @selected($filters->sort === 'terlaris')>Terlaris</option>
                @endif
            </select>
        </div>

        <div class="catalog-filters__field">
            <label for="filterStok">Stok</label>
            <select name="stok" id="filterStok">
                <option value="all" @selected($filters->stok === 'all')>Semua</option>
                <option value="ada" @selected($filters->stok === 'ada')>Hanya tersedia</option>
                <option value="habis" @selected($filters->stok === 'habis')>Stok habis</option>
            </select>
        </div>

        <div class="catalog-filters__field">
            <label for="filterPromo">Promo</label>
            <select name="promo" id="filterPromo">
                <option value="all" @selected($filters->promo === 'all')>Semua produk</option>
                <option value="diskon" @selected($filters->promo === 'diskon')>Diskon aktif</option>
            </select>
        </div>

        <button type="submit" class="btn block catalog-filters__apply">Terapkan filter</button>
    </form>
</aside>
