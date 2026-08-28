@php
    $slides = [
        [
            'tone' => 'primary',
            'tag' => $branchLabel ?? 'Cabang member',
            'title' => 'Harga '.$tierLabel.' · '.($branchLabel ?? ''),
            'text' => 'Minimal belanja Rp '.number_format($minOrder ?? 0, 0, ',', '.').' · COD setelah verifikasi admin.',
            'cta' => null,
            'href' => null,
        ],
    ];

    if (($discounted ?? collect())->isNotEmpty()) {
        $slides[] = [
            'tone' => 'accent',
            'tag' => 'Flash sale',
            'title' => 'Diskon aktif hari ini',
            'text' => 'Produk pilihan dengan harga spesial member '.$tierLabel.'.',
            'cta' => 'Lihat diskon',
            'href' => '#flash-sale',
        ];
    }

    if (auth()->guest()) {
        $slides[] = [
            'tone' => 'violet',
            'tag' => 'Member NU Mart',
            'title' => 'Daftar & nikmati harga khusus',
            'text' => 'Login dengan nomor kartu member untuk harga grosir dan poin belanja.',
            'cta' => 'Daftar sekarang',
            'href' => route('login'),
        ];
    } elseif (! in_array(auth()->user()->member_verification_status ?? 'none', ['approved', 'pending'], true)) {
        $slides[] = [
            'tone' => 'violet',
            'tag' => 'Verifikasi',
            'title' => 'Aktifkan COD setelah verifikasi',
            'text' => 'Upload KTP di profil — diverifikasi kasir POS cabang.',
            'cta' => 'Verifikasi akun',
            'href' => route('member.verification.create'),
        ];
    } else {
        $slides[] = [
            'tone' => 'teal',
            'tag' => 'Belanja mudah',
            'title' => 'Terlaris minggu ini',
            'text' => 'Cek produk favorit member lainnya di katalog.',
            'cta' => 'Lihat terlaris',
            'href' => route('shop.index', ['tipe' => 'terlaris']),
        ];
    }
@endphp

<div class="hero-carousel" data-hero-carousel aria-label="Promo">
    <div class="hero-carousel__track">
        @foreach($slides as $i => $slide)
            <article class="hero-slide hero-slide--{{ $slide['tone'] }}" data-hero-slide="{{ $i }}">
                <span class="hero-slide__tag">{{ $slide['tag'] }}</span>
                <h2 class="hero-slide__title">{{ $slide['title'] }}</h2>
                <p class="hero-slide__text">{{ $slide['text'] }}</p>
                @if($slide['cta'] && $slide['href'])
                    <a href="{{ $slide['href'] }}" class="hero-slide__cta">{{ $slide['cta'] }}</a>
                @endif
            </article>
        @endforeach
    </div>
    @if(count($slides) > 1)
        <div class="hero-carousel__controls">
            <button type="button" class="hero-carousel__arrow hero-carousel__arrow--prev" data-hero-prev aria-label="Slide sebelumnya">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
            </button>
            <div class="hero-carousel__dots" role="tablist" aria-label="Pilih slide">
                @foreach($slides as $i => $slide)
                    <button type="button" class="hero-carousel__dot{{ $i === 0 ? ' is-active' : '' }}" data-hero-dot="{{ $i }}" aria-label="Slide {{ $i + 1 }}" aria-selected="{{ $i === 0 ? 'true' : 'false' }}"></button>
                @endforeach
            </div>
            <button type="button" class="hero-carousel__arrow hero-carousel__arrow--next" data-hero-next aria-label="Slide berikutnya">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
            </button>
        </div>
    @endif
</div>
