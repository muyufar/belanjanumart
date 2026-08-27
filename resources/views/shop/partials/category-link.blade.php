@php
    $href = $href ?? '#';
    $label = $label ?? '';
    $active = $active ?? false;
    $visual = $visual ?? [];
@endphp
<a href="{{ $href }}" class="cat-item {{ $active ? 'is-active' : '' }}">
    @include('shop.partials.category-icon', ['visual' => $visual])
    <span>{{ $label }}</span>
</a>
