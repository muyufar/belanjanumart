<?php

namespace App\Services;

class PricingService
{
    /**
     * Tier: 0 umum, 1 retail, 2 grosir — selaras customer_category Numart.
     */
    public function unitPrice(object $barang, int $tier): int
    {
        return match ($tier) {
            1 => (int) ($barang->barang_harga_grosir_1 ?? 0),
            2 => (int) ($barang->barang_harga_grosir_2 ?? 0),
            default => (int) ($barang->barang_harga ?? 0),
        };
    }

    public function comparePrice(object $barang, int $tier): ?int
    {
        $member = $this->unitPrice($barang, $tier);
        $umum = (int) ($barang->barang_harga ?? 0);

        if ($tier < 1 || $umum <= 0 || $member >= $umum) {
            return null;
        }

        return $umum;
    }

    public function tierLabel(int $tier): string
    {
        return match ($tier) {
            1 => 'Retail',
            2 => 'Grosir',
            default => 'Umum',
        };
    }

    public function tierForUser(?\App\Models\User $user): int
    {
        if (! $user) {
            return 0;
        }

        return match ((int) $user->price_tier) {
            2 => 2,
            1 => 1,
            default => 0,
        };
    }
}
