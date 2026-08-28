@php
    $icon = $icon ?? 'box';
@endphp
<span class="cat-item__mark cat-item__mark--svg" aria-hidden="true">
@switch($icon)
    @case('water')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2.69l5.66 5.66a8 8 0 11-11.32 0z"/></svg>
        @break
    @case('bulb')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18h6M10 22h4M12 2a6 6 0 00-3 11v1h6v-1a6 6 0 00-3-11z"/></svg>
        @break
    @case('watch')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="7"/><path d="M12 9v4l2 2M9 2h6M9 22h6"/></svg>
        @break
    @case('pencil')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
        @break
    @case('rice')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 12c0-3 2-5 6-5s6 2 6 5-2 7-6 7-6-4-6-7z"/><path d="M8 10h8M9 14h6"/></svg>
        @break
    @case('drink')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 2h8l-1 9a4 4 0 01-8 0L8 2zM7 22h10"/></svg>
        @break
    @case('snack')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="6" width="16" height="12" rx="2"/><path d="M8 6V4M12 6V4M16 6V4"/></svg>
        @break
    @case('bottle')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 2h4v3a4 4 0 002 3.5V20a2 2 0 01-2 2h-4a2 2 0 01-2-2V8.5A4 4 0 0110 5V2z"/></svg>
        @break
    @case('milk')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 2h8v4l-1 16H9L8 6V2z"/></svg>
        @break
    @case('meat')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4c2 2 2 5-1 8l-5 5-3-3 5-5c3-3 6-3 4-5z"/><path d="M5 19l2-2"/></svg>
        @break
    @case('leaf')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 20c6-1 11-6 12-12-6 1-11 6-12 12z"/><path d="M6 20c3-3 7-5 12-6"/></svg>
        @break
    @case('bread')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12a8 8 0 0116 0v2a3 3 0 01-3 3H7a3 3 0 01-3-3v-2z"/></svg>
        @break
    @case('baby')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="10" r="4"/><path d="M6 20c0-3 2.5-5 6-5s6 2 6 5"/></svg>
        @break
    @case('pill')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8.5 8.5l7 7M9 5.5a5 5 0 017 7l-7 7a5 5 0 01-7-7l7-7z"/></svg>
        @break
    @case('sparkle')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3l1.5 5.5L19 10l-5.5 1.5L12 17l-1.5-5.5L5 10l5.5-1.5L12 3zM18 16l.8 2.2L21 19l-2.2.8L18 22l-.8-2.2L15 19l2.2-.8L18 16z"/></svg>
        @break
    @case('ice')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="5" width="14" height="14" rx="2" transform="rotate(45 12 12)"/></svg>
        @break
    @case('home')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 10.5L12 4l9 6.5V20a1 1 0 01-1 1h-5v-6H9v6H4a1 1 0 01-1-1v-9.5z"/></svg>
        @break
    @case('fire')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3c2 4 5 6 5 10a5 5 0 01-10 0c0-2 1-3 2-5-1 2-1 4 1 6 0-4 2-7 2-11z"/></svg>
        @break
    @case('battery')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="18" height="10" rx="2"/><path d="M22 11v2M6 11h4M6 14h2"/></svg>
        @break
    @case('gas')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="14" rx="6" ry="8"/><path d="M12 6V4M9 4h6"/></svg>
        @break
    @case('dessert')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 14h8l-1 6H9l-1-6zM12 4v4M9 8h6"/></svg>
        @break
    @default
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
@endswitch
</span>
