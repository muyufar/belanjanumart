<div class="grid">
    @foreach($products as $p)
        @php
            $quickPayload = [
                'id' => (int) $p->barang_id,
                'name' => $p->barang_nama,
                'price' => (int) $p->price,
                'priceOriginal' => ! empty($p->price_original) ? (int) $p->price_original : null,
                'hasDiscount' => ! empty($p->has_discount),
                'discountLabel' => $p->discount_label ?? null,
                'image' => $p->image_url ?? null,
                'inStock' => ! empty($p->in_stock),
                'stock' => (int) ($p->stock ?? 0),
                'kode' => $p->barang_kode ?? '',
                'url' => route('shop.show', $p->barang_id),
            ];
        @endphp
        <article class="product-card {{ empty($p->in_stock) ? 'product-card--oos' : '' }}">
            <a href="{{ route('shop.show', $p->barang_id) }}" class="product-card__link">
                @include('shop.partials.product-image', ['product' => $p])
                <div class="product-card__body">
                    <h3>{{ $p->barang_nama }}</h3>
                    @include('shop.partials.product-stock', ['product' => $p])
                    @if(!empty($p->price_original) && $p->price_original > $p->price)
                        <div class="product-card__price product-card__price--sale">Rp {{ number_format($p->price, 0, ',', '.') }}</div>
                        <div class="product-card__price-old">Rp {{ number_format($p->price_original, 0, ',', '.') }}</div>
                        @if($p->discount_label)<span class="badge-discount">{{ $p->discount_label }}</span>@endif
                    @elseif(!empty($p->has_discount) && $p->price_original)
                        <div class="product-card__price product-card__price--sale">Rp {{ number_format($p->price, 0, ',', '.') }}</div>
                        <div class="product-card__price-old">Rp {{ number_format($p->price_original, 0, ',', '.') }}</div>
                        @if($p->discount_label)<span class="badge-discount">{{ $p->discount_label }}</span>@endif
                    @else
                        <div class="product-card__price">Rp {{ number_format($p->price, 0, ',', '.') }}</div>
                    @endif
                </div>
            </a>
            <button type="button"
                    class="product-card__quick"
                    data-quick-view='@json($quickPayload)'
                    aria-label="Lihat cepat {{ $p->barang_nama }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                <span>Lihat cepat</span>
            </button>
        </article>
    @endforeach
</div>
