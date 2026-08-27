@php
    $stockQty = (float) ($product->stock ?? $product->barang_stock ?? 0);
    $inStock = $stockQty > 0;
    $stockLabel = $inStock
        ? ($stockQty == floor($stockQty)
            ? number_format($stockQty, 0, ',', '.')
            : rtrim(rtrim(number_format($stockQty, 2, ',', '.'), '0'), ','))
        : null;
@endphp
<p class="product-stock {{ $inStock ? '' : 'product-stock--empty' }} {{ $class ?? '' }}">
    @if($inStock)
        Stok: {{ $stockLabel }}
    @else
        Stok habis
    @endif
</p>
