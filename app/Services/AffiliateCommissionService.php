<?php

namespace App\Services;

use App\Models\AffiliateCommission;
use App\Models\Order;
use App\Models\User;

class AffiliateCommissionService
{
    public function __construct(
        protected AffiliateSettingsService $settings,
    ) {}

    public function accrueForOrder(Order $order): ?AffiliateCommission
    {
        if (! in_array($order->status, ['processing', 'paid', 'shipped', 'completed'], true)) {
            return null;
        }

        if (AffiliateCommission::where('order_id', $order->id)->exists()) {
            return AffiliateCommission::where('order_id', $order->id)->first();
        }

        $buyer = $order->user;
        if (! $buyer?->affiliate_kader_id) {
            return null;
        }

        $affiliate = User::find($buyer->affiliate_kader_id);
        if (! $affiliate || ! $affiliate->isAffiliateKader() || ! $affiliate->affiliate_active) {
            return null;
        }

        $order->loadMissing('items');

        // ERP tidak membuka HPP ke mitra → margin tidak bisa dihitung. Pesanan ERP memakai % per jenis mitra
        // (warung / prioritas / referral) dari subtotal atau grand total — diatur di Backoffice → Komisi.
        if ($order->numart_source === 'erp' && $order->items->contains(fn ($item) => (int) $item->harga_beli <= 0)) {
            return $this->accrueByPartnerRate($order, $affiliate, $buyer);
        }

        $subtotal = (int) $order->subtotal;
        $cost = (int) $order->items->sum(fn ($item) => (int) $item->harga_beli * (int) $item->qty);
        $netMargin = max(0, $subtotal - $cost);

        $sharePercent = $this->settings->marginSharePercentForOrder($subtotal, $netMargin);
        if ($sharePercent <= 0) {
            return null;
        }

        $amount = (int) floor($netMargin * $sharePercent / 100);

        return AffiliateCommission::create([
            'affiliate_user_id' => $affiliate->id,
            'referred_user_id' => $buyer->id,
            'order_id' => $order->id,
            'partner_kind' => $buyer->partner_kind ?? 'referral',
            'order_base_amount' => $subtotal,
            'order_net_margin' => $netMargin,
            'margin_share_percent' => $sharePercent,
            'commission_percent' => $sharePercent,
            'commission_amount' => $amount,
            'status' => AffiliateCommission::STATUS_ACCRUED,
            'accrued_at' => now(),
        ]);
    }

    /** Komisi = % jenis mitra × dasar (subtotal / grand total), tanpa HPP. */
    protected function accrueByPartnerRate(Order $order, User $affiliate, User $buyer): ?AffiliateCommission
    {
        $settings = $this->settings->all();
        $kind = $buyer->partner_kind ?: 'referral';
        $percent = $this->settings->percentForPartnerKind($kind);
        $base = $settings['basis'] === 'grand_total' ? (int) $order->grand_total : (int) $order->subtotal;

        if ($percent <= 0 || $base <= 0) {
            return null;
        }

        return AffiliateCommission::create([
            'affiliate_user_id' => $affiliate->id,
            'referred_user_id' => $buyer->id,
            'order_id' => $order->id,
            'partner_kind' => $kind,
            'order_base_amount' => $base,
            'order_net_margin' => 0,             // HPP tidak diketahui di ERP
            'margin_share_percent' => 0,
            'commission_percent' => $percent,
            'commission_amount' => (int) floor($base * $percent / 100),
            'status' => AffiliateCommission::STATUS_ACCRUED,
            'accrued_at' => now(),
        ]);
    }

    public function markPaid(AffiliateCommission $commission, ?string $note = null): AffiliateCommission
    {
        $commission->update([
            'status' => AffiliateCommission::STATUS_PAID,
            'paid_at' => now(),
            'paid_note' => $note,
        ]);

        return $commission->fresh();
    }

    /** @return array{accrued: int, paid: int, count_accrued: int, count_paid: int} */
    public function totalsForAffiliate(int $affiliateUserId): array
    {
        $accrued = (int) AffiliateCommission::query()
            ->where('affiliate_user_id', $affiliateUserId)
            ->where('status', AffiliateCommission::STATUS_ACCRUED)
            ->sum('commission_amount');

        $paid = (int) AffiliateCommission::query()
            ->where('affiliate_user_id', $affiliateUserId)
            ->where('status', AffiliateCommission::STATUS_PAID)
            ->sum('commission_amount');

        $countAccrued = AffiliateCommission::query()
            ->where('affiliate_user_id', $affiliateUserId)
            ->where('status', AffiliateCommission::STATUS_ACCRUED)
            ->count();

        $countPaid = AffiliateCommission::query()
            ->where('affiliate_user_id', $affiliateUserId)
            ->where('status', AffiliateCommission::STATUS_PAID)
            ->count();

        return [
            'accrued' => $accrued,
            'paid' => $paid,
            'count_accrued' => $countAccrued,
            'count_paid' => $countPaid,
        ];
    }
}
