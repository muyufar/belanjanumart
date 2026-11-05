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
        if (! $buyer?->affiliate_kader_id || ! $buyer->partner_kind) {
            return null;
        }

        $affiliate = User::find($buyer->affiliate_kader_id);
        if (! $affiliate || ! $affiliate->isAffiliateKader() || ! $affiliate->affiliate_active) {
            return null;
        }

        $cfg = $this->settings->all();
        $percent = $this->settings->percentForPartnerKind($buyer->partner_kind);
        if ($percent <= 0) {
            return null;
        }

        $base = $cfg['basis'] === 'grand_total' ? (int) $order->grand_total : (int) $order->subtotal;
        $amount = (int) floor($base * $percent / 100);

        return AffiliateCommission::create([
            'affiliate_user_id' => $affiliate->id,
            'referred_user_id' => $buyer->id,
            'order_id' => $order->id,
            'partner_kind' => $buyer->partner_kind,
            'order_base_amount' => $base,
            'commission_percent' => $percent,
            'commission_amount' => $amount,
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
