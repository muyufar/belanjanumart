@php
    $visual = $visual ?? [];
    $kind = $visual['kind'] ?? 'icon';
    $from = $visual['from'] ?? '#5B8DEF';
    $to = $visual['to'] ?? '#7B5BEF';
    $tone = $visual['tone'] ?? 'default';
@endphp
<div class="cat-item__icon cat-item__icon--{{ $tone }}"
     style="--cat-from: {{ $from }}; --cat-to: {{ $to }};">
    <span class="cat-item__icon-bg" aria-hidden="true"></span>
    @if($kind === 'grid')
        <span class="cat-item__mark cat-item__mark--svg" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
        </span>
    @elseif($kind === 'letter')
        <span class="cat-item__mark cat-item__mark--letter" aria-hidden="true">{{ $visual['letter'] ?? '?' }}</span>
    @else
        @include('shop.partials.category-svg-icon', ['icon' => $visual['icon'] ?? 'box'])
    @endif
</div>
