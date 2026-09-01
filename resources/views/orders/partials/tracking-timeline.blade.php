@php
    /** @var array<int, array{key: string, label: string, done: bool, active: bool}> $trackingSteps */
@endphp
<div class="order-tracking panel">
    <div class="order-tracking__head">
        <h3 class="order-tracking__title">Status pengiriman</h3>
        @if(!empty($trackingLabel))
            <span class="order-tracking__badge">{{ $trackingLabel }}</span>
        @endif
    </div>

    <ol class="order-tracking__steps" aria-label="Progres pengiriman">
        @foreach($trackingSteps as $step)
            <li class="order-tracking__step{{ $step['done'] ? ' is-done' : '' }}{{ $step['active'] ? ' is-active' : '' }}">
                <span class="order-tracking__dot" aria-hidden="true">
                    @if($step['done'])
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                    @endif
                </span>
                <span class="order-tracking__label">{{ $step['label'] }}</span>
            </li>
        @endforeach
    </ol>

    @if(!empty($order->tracking_note))
        <p class="order-tracking__note muted">{{ $order->tracking_note }}</p>
    @endif

    @if($order->tracking_updated_at)
        <p class="order-tracking__updated muted">Diperbarui {{ $order->tracking_updated_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</p>
    @endif
</div>
