@php
    $product = $product ?? null;
    $hasImage = !empty($product?->image_url);
@endphp
<div class="product-card__img-wrap">
    @if(!empty($product->has_discount))
        <span class="product-card__badge">Diskon</span>
    @endif
    @include('shop.partials.product-placeholder', ['product' => $product, 'hidden' => $hasImage])
    @if($hasImage)
        <img
            src="{{ $product->image_url }}"
            alt="{{ $product->barang_nama }}"
            loading="lazy"
            decoding="async"
            onerror="this.style.display='none';var p=this.previousElementSibling;if(p)p.classList.remove('product-placeholder--hidden');"
        >
    @endif
</div>
