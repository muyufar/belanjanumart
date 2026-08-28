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
                <div class="panel cart-item" style="margin-bottom:10px">
                    <div>
                        <strong>{{ $item['barang_nama'] }}</strong>
                        <div class="muted">Rp {{ number_format($item['price'], 0, ',', '.') }} × {{ $item['qty'] }}</div>
                    </div>
                    <div style="text-align:right">
                        <div class="product-card__price">Rp {{ number_format($item['price'] * $item['qty'], 0, ',', '.') }}</div>
                        <form method="post" action="{{ route('cart.update', $item['barang_id']) }}" style="margin-top:8px">
                            @csrf @method('PATCH')
                            <input type="number" name="qty" value="{{ $item['qty'] }}" min="0" max="99" class="qty-input" style="width:64px" onchange="this.form.submit()" aria-label="Jumlah">
                        </form>
                    </div>
                </div>
            @empty
                <div class="panel" style="text-align:center;padding:40px 20px">
                    <p class="muted">Keranjang masih kosong</p>
                    <a href="{{ route('shop.index') }}" class="btn block" style="margin-top:16px">Mulai belanja</a>
                </div>
            @endforelse
        </div>

        @if(count($items) > 0)
            <div class="cart-layout__summary">
                <div class="panel">
                    <div class="summary-row total">
                        <span>Subtotal</span>
                        <span>Rp {{ number_format($subtotal, 0, ',', '.') }}</span>
                    </div>
                    <a href="{{ route('checkout.create') }}" class="btn block" style="margin-top:12px">Checkout</a>
                </div>
            </div>
        @endif
    </div>
@endsection
