@extends('layouts.app')

@section('title', 'Komisi affiliate')

@section('content')
    <h1>Komisi mitra kader</h1>

    <p>
        <a href="{{ route('admin.affiliate.commissions', ['status' => 'accrued']) }}" class="{{ $status === 'accrued' ? 'tier-pill' : '' }}">Menunggu bayar</a>
        <a href="{{ route('admin.affiliate.commissions', ['status' => 'paid']) }}" class="{{ $status === 'paid' ? 'tier-pill' : '' }}">Sudah dibayar</a>
    </p>

    @foreach($commissions as $c)
        <div class="panel" style="margin-bottom:10px;padding:12px">
            <strong>{{ $c->affiliate?->name }}</strong> · {{ $c->order?->order_number }}
            <div class="muted">{{ $c->referredUser?->name }} · {{ $c->partner_kind }} · {{ $c->commission_percent }}%</div>
            <div>Rp {{ number_format($c->commission_amount, 0, ',', '.') }} dari basis Rp {{ number_format($c->order_base_amount, 0, ',', '.') }}</div>
            @if($c->status === 'accrued')
                <form method="post" action="{{ route('admin.affiliate.commissions.pay', $c) }}" style="margin-top:8px">
                    @csrf
                    <input type="text" name="paid_note" placeholder="Catatan transfer (opsional)" style="width:100%;margin-bottom:6px">
                    <button type="submit" class="btn secondary">Tandai sudah dibayar</button>
                </form>
            @else
                <div class="muted" style="font-size:0.85rem">Dibayar {{ $c->paid_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}</div>
            @endif
        </div>
    @endforeach

    {{ $commissions->links() }}
@endsection
