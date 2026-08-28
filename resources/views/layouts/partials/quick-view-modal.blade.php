<div class="quick-view" id="quickView" hidden aria-hidden="true">
    <div class="quick-view__backdrop" data-quick-close></div>
    <div class="quick-view__dialog" role="dialog" aria-modal="true" aria-labelledby="quickViewTitle">
        <button type="button" class="quick-view__close" data-quick-close aria-label="Tutup">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>
        <div class="quick-view__grid">
            <div class="quick-view__media">
                <img src="" alt="" class="quick-view__img" id="quickViewImg" hidden>
                <div class="quick-view__placeholder" id="quickViewPlaceholder"></div>
            </div>
            <div class="quick-view__body">
                <h2 class="quick-view__title" id="quickViewTitle"></h2>
                <p class="quick-view__kode muted" id="quickViewKode"></p>
                <p class="quick-view__stock" id="quickViewStock"></p>
                <div class="quick-view__prices" id="quickViewPrices"></div>
                <form method="post" action="{{ route('cart.store') }}" class="quick-view__form" id="quickViewForm">
                    @csrf
                    <input type="hidden" name="barang_id" id="quickViewBarangId" value="">
                    <div class="quick-view__actions">
                        <input type="number" name="qty" value="1" min="1" max="99" class="qty-input" id="quickViewQty" aria-label="Jumlah">
                        <button type="submit" class="btn" id="quickViewSubmit">Tambah keranjang</button>
                    </div>
                </form>
                <a href="#" class="quick-view__detail" id="quickViewDetail">Lihat detail produk →</a>
            </div>
        </div>
    </div>
</div>
