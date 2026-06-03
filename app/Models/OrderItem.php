<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'barang_id',
        'barang_kode',
        'barang_nama',
        'qty',
        'unit_price',
        'line_total',
        'harga_beli',
        'satuan_id',
        'konversi_isi',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
