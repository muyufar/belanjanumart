<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'provider',
        'cust_code',
        'virtual_account',
        'amount',
        'status',
        'bri_response',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'bri_response' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
