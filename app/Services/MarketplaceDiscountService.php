<?php

namespace App\Services;

use App\Services\Erp\ErpBranchMap;
use App\Services\Erp\ErpSettings;
use App\Support\NumartSource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MarketplaceDiscountService
{
    public function tableExists(): bool
    {
        if (NumartSource::erp()) {
            return true;
        }

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
        if (NumartSource::erp()) {
            return $this->erpDiscountsByKode();
        }

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

    /**
     * O-4: diskon online aktif hari ini dari Backoffice ERP, dalam bentuk baris `marketplace_diskon` POS lama.
     * Diskon khusus cabang hanya dipakai bila cabangnya = cabang tampilan katalog (POS lama tidak punya diskon per cabang).
     *
     * @return Collection<string, object>
     */
    protected function erpDiscountsByKode(): Collection
    {
        try {
            $rows = app(ErpSettings::class)->all()['diskon'] ?? [];
            $displayErp = app(ErpBranchMap::class)->map()[(int) config('marketplace.catalog_cabang_display', 0)] ?? null;
        } catch (\Throwable $e) {
            report($e);

            return collect();
        }

        return collect($rows)
            ->filter(fn ($d) => ($d['cabang_id'] ?? null) === null || (int) $d['cabang_id'] === $displayErp)
            ->map(fn ($d) => (object) [
                'barang_kode' => (string) $d['kode'],
                'erp_barang_id' => (int) $d['barang_id'],
                'diskon_tipe' => (string) $d['tipe'],
                'diskon_nilai' => (float) $d['nilai'],
                'mulai' => $d['mulai'] ?? null,
                'selesai' => $d['selesai'] ?? null,
                'aktif' => 1,
            ])
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
