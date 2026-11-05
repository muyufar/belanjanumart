<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateReferral extends Model
{
    protected $fillable = [
        'affiliate_user_id',
        'referred_user_id',
        'numart_customer_id',
        'member_card',
        'partner_kind',
        'customer_name',
        'customer_phone',
        'warung_name',
    ];

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'affiliate_user_id');
    }

    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }
}
