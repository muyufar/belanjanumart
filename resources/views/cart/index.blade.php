@extends('layouts.app')

@section('title', 'Keranjang')
@section('page_class', 'page--cart')

@section('breadcrumb')
    @include('layouts.partials.breadcrumb', ['items' => [
        ['label' => 'Beranda', 'url' => route('shop.index')],
        ['label' => 'Keranjang'],
    ]])
@endsection

@section('content')
    <div class="section-head">
        <h2 style="margin:0;font-size:1.25rem">Keranjang</h2>
        <span class="tier-pill">{{ $tierLabel }}</span>
    </div>

    <div class="cart-layout">
        <div class="cart-layout__items">
            @forelse($items as $item)
                <article class="cart-row panel">
                    @if(!empty($item['image_url']))
                        <a href="{{ route('shop.show', $item['barang_id']) }}" class="cart-row__thumb">
                            <img src="{{ $item['image_url'] }}" alt="" loading="lazy">
                        </a>
                    @else
                        <a href="{{ route('shop.show', $item['barang_id']) }}" class="cart-row__thumb cart-row__thumb--empty" aria-hidden="true">
                            <span>{{ strtoupper(mb_substr($item['barang_nama'], 0, 1)) }}</span>
                        </a>
                    @endif

                    <div class="cart-row__body">
                        <a href="{{ route('shop.show', $item['barang_id']) }}" class="cart-row__name">{{ $item['barang_nama'] }}</a>
                        <p class="cart-row__unit muted">Rp {{ number_format($item['price'], 0, ',', '.') }} / item</p>
                        <form method="post" action="{{ route('cart.destroy', $item['barang_id']) }}" class="cart-row__remove-form">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="cart-row__remove">Hapus</button>
                        </form>
                    </div>

                    <div class="cart-row__actions">
                        <p class="cart-row__total">Rp {{ number_format($item['price'] * $item['qty'], 0, ',', '.') }}</p>
                        <form method="post" action="{{ route('cart.update', $item['barang_id']) }}" class="cart-row__qty-form">
                            @csrf
                            @method('PATCH')
                            <div class="qty-stepper" data-qty-stepper>
                                <button type="button" class="qty-stepper__btn" data-qty-minus aria-label="Kurangi jumlah">−</button>
                                <input type="number" name="qty" value="{{ $item['qty'] }}" min="0" max="99" class="qty-stepper__input" aria-label="Jumlah">
                                <button type="button" class="qty-stepper__btn" data-qty-plus aria-label="Tambah jumlah">+</button>
                            </div>
                        </form>
                    </div>
                </article>
            @empty
                <div class="panel cart-empty">
                    <p class="muted" style="margin:0">Keranjang masih kosong</p>
                    <a href="{{ route('shop.index') }}" class="btn block" style="margin-top:16px">Mulai belanja</a>
                </div>
            @endforelse
        </div>

        @if(count($items) > 0)
            <aside class="cart-layout__summary">
                <div class="panel cart-summary">
                    <h3 class="cart-summary__title">Ringkasan</h3>
                    <div class="summary-row">
                        <span>Subtotal ({{ count($items) }} item)</span>
                        <span>Rp {{ number_format($subtotal, 0, ',', '.') }}</span>
                    </div>
                    @if($subtotal < ($minOrder ?? 0))
                        <p class="cart-summary__hint muted">
                            Minimal belanja {{ $tierLabel }}: Rp {{ number_format($minOrder, 0, ',', '.') }}
                        </p>
                    @endif
                    <a href="{{ route('checkout.create') }}" class="btn block" style="margin-top:16px">Checkout</a>
                    <a href="{{ route('shop.index') }}" class="cart-summary__continue">← Lanjut belanja</a>
                </div>
            </aside>
        @endif
    </div>
@endsection

@push('scripts')
<script src="{{ asset('js/cart-qty.js') }}" defer></script>
@endpush
