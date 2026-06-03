@php
    $product = $product ?? null;
    $hidden = $hidden ?? false;
    $size = $size ?? 'card';
    $id = (int) ($product->barang_id ?? 0);
    $seed = (string) ($product->barang_kode ?? $id);
    $h = abs(crc32($seed));
    $palettes = [
        ['#e8f7f0', '#c5ead8', '#2d9b6e'],
        ['#e8f0ff', '#c5d8f5', '#5b8def'],
        ['#fff0eb', '#ffd4c5', '#ff7a50'],
        ['#f0ebff', '#d4c5ff', '#7b5bef'],
        ['#e8faf9', '#c5efe8', '#4ecdc4'],
        ['#fff8e6', '#ffe8b8', '#f7b731'],
    ];
    $pal = $palettes[$h % count($palettes)];
    $initials = '';
    if ($product && !empty($product->barang_nama)) {
        $words = preg_split('/\s+/', trim($product->barang_nama), 3);
        $initials = mb_strtoupper(mb_substr($words[0] ?? '', 0, 1));
        if (isset($words[1]) && $words[1] !== '') {
            $initials .= mb_strtoupper(mb_substr($words[1], 0, 1));
        }
    }
    if ($initials === '') {
        $initials = 'NM';
    }
@endphp
<div class="product-placeholder product-placeholder--{{ $size }} {{ $hidden ? 'product-placeholder--hidden' : '' }}"
     style="--ph-from: {{ $pal[0] }}; --ph-mid: {{ $pal[1] }}; --ph-accent: {{ $pal[2] }};"
     aria-hidden="true">
    <svg class="product-placeholder__icon" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="12" y="18" width="40" height="34" rx="6" stroke="currentColor" stroke-width="2.5" opacity="0.35"/>
        <path d="M22 18V14a10 10 0 0120 0v4" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" opacity="0.35"/>
        <circle cx="32" cy="36" r="8" fill="currentColor" opacity="0.12"/>
    </svg>
    <span class="product-placeholder__initials">{{ $initials }}</span>
</div>
