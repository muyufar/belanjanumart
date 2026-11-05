<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateCommission extends Model
{
    public const STATUS_ACCRUED = 'accrued';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'affiliate_user_id',
        'referred_user_id',
        'order_id',
        'partner_kind',
        'order_base_amount',
        'order_net_margin',
        'margin_share_percent',
        'commission_percent',
        'commission_amount',
        'status',
        'accrued_at',
        'paid_at',
        'paid_note',
    ];

    protected function casts(): array
    {
        return [
            'commission_percent' => 'float',
            'accrued_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'affiliate_user_id');
    }

    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
