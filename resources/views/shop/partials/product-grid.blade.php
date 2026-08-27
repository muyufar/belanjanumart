<div class="grid">
    @foreach($products as $p)
        <a href="{{ route('shop.show', $p->barang_id) }}" class="product-card">
            @include('shop.partials.product-image', ['product' => $p])
            <div class="product-card__body">
                <h3>{{ $p->barang_nama }}</h3>
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
    @endforeach
</div>
