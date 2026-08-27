<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'user_id',
        'price_tier',
        'fulfillment_cabang',
        'fulfillment_label',
        'customer_lat',
        'customer_lng',
        'customer_name',
        'customer_phone',
        'customer_address',
        'subtotal',
        'shipping_fee',
        'discount',
        'grand_total',
        'status',
        'payment_method',
        'payment_proof_path',
        'payment_proof_at',
        'numart_invoice',
        'paid_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
            'payment_proof_at' => 'datetime',
            'customer_lat' => 'float',
            'customer_lng' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function stockHolds(): HasMany
    {
        return $this->hasMany(StockHold::class);
    }

    public function isPaid(): bool
    {
        return in_array($this->status, ['paid', 'processing', 'shipped', 'completed'], true);
    }
}
