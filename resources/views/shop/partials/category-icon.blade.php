@php
    $visual = $visual ?? [];
    $kind = $visual['kind'] ?? 'emoji';
    $accent = $visual['accent'] ?? '#2d9b6e';
    $tone = $visual['tone'] ?? 'default';
@endphp
<div class="cat-item__icon cat-item__icon--{{ $tone }}"
     style="--cat-accent: {{ $accent }};">
    @if($kind === 'letter')
        <span class="cat-item__glyph cat-item__glyph--letter">{{ $visual['letter'] ?? '?' }}</span>
    @else
        <span class="cat-item__glyph cat-item__glyph--emoji">{{ $visual['emoji'] ?? '🏪' }}</span>
    @endif
</div>
