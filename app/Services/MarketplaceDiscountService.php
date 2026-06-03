<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MarketplaceDiscountService
{
    public function tableExists(): bool
    {
        try {
            return Schema::connection('numart')->hasTable('marketplace_diskon');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return Collection<string, object> keyed by barang_kode
     */
    public function activeDiscountsByKode(): Collection
    {
        if (! $this->tableExists()) {
            return collect();
        }

        $today = now('Asia/Jakarta')->toDateString();

        return DB::connection('numart')
            ->table('marketplace_diskon')
            ->where('aktif', 1)
            ->where(function ($q) use ($today) {
                $q->whereNull('mulai')->orWhere('mulai', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('selesai')->orWhere('selesai', '>=', $today);
            })
            ->orderByDesc('diskon_id')
            ->get()
            ->unique('barang_kode')
            ->keyBy('barang_kode');
    }

    public function applyDiscount(int $basePrice, ?object $discount): array
    {
        if (! $discount || $basePrice <= 0) {
            return [
                'price' => $basePrice,
                'price_original' => null,
                'has_discount' => false,
                'discount_label' => null,
            ];
        }

        $tipe = (string) ($discount->diskon_tipe ?? 'persen');
        $nilai = (float) ($discount->diskon_nilai ?? 0);

        $final = $basePrice;
        $label = null;

        if ($tipe === 'harga' && $nilai > 0) {
            $final = (int) round($nilai);
            $label = 'Harga promo';
        } elseif ($tipe === 'persen' && $nilai > 0) {
            $final = (int) round($basePrice * (1 - ($nilai / 100)));
            $label = rtrim(rtrim(number_format($nilai, 2, ',', '.'), '0'), ',').'%';
        }

        $final = max(0, $final);

        if ($final >= $basePrice) {
            return [
                'price' => $basePrice,
                'price_original' => null,
                'has_discount' => false,
                'discount_label' => null,
            ];
        }

        return [
            'price' => $final,
            'price_original' => $basePrice,
            'has_discount' => true,
            'discount_label' => $label,
        ];
    }
}
