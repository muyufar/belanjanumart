@php
    $visual = $visual ?? [];
    $kind = $visual['kind'] ?? 'emoji';
    $from = $visual['from'] ?? '#5B8DEF';
    $to = $visual['to'] ?? '#7B5BEF';
    $accent = $visual['accent'] ?? $from;
    $tone = $visual['tone'] ?? 'default';
@endphp
<div class="cat-item__icon cat-item__icon--{{ $tone }}"
     style="--cat-from: {{ $from }}; --cat-to: {{ $to }}; --cat-accent: {{ $accent }};">
    <span class="cat-item__icon-bg" aria-hidden="true"></span>
    <span class="cat-item__mark" aria-hidden="true">
        <span class="cat-item__mark-pad">
            @if($kind === 'letter')
                <span class="cat-item__mark--letter">{{ $visual['letter'] ?? '?' }}</span>
            @else
                <span class="cat-item__mark--emoji">{{ $visual['emoji'] ?? '🏪' }}</span>
            @endif
        </span>
    </span>
</div>
