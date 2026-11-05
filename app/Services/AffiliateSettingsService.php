<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AffiliateSettingsService
{
    private const CACHE_KEY = 'affiliate.settings';

    /** @return array{warung: float, prioritas: float, basis: string, margin_share_min: float, margin_share_max: float} */
    public function all(): array
    {
        if (! Schema::hasTable('affiliate_settings')) {
            return $this->defaults();
        }

        return Cache::remember(self::CACHE_KEY, 60, function () {
            $rows = DB::table('affiliate_settings')->pluck('value', 'key');
            $defaults = $this->defaults();

            return [
                'warung' => (float) ($rows['commission_warung_percent'] ?? $defaults['warung']),
                'prioritas' => (float) ($rows['commission_prioritas_percent'] ?? $defaults['prioritas']),
                'basis' => (string) ($rows['commission_basis'] ?? $defaults['basis']),
                'margin_share_min' => (float) ($rows['commission_margin_share_min'] ?? $defaults['margin_share_min']),
                'margin_share_max' => (float) ($rows['commission_margin_share_max'] ?? $defaults['margin_share_max']),
            ];
        });
    }

    /** @return array{warung: float, prioritas: float, basis: string, margin_share_min: float, margin_share_max: float} */
    public function defaults(): array
    {
        return [
            'warung' => (float) config('marketplace.affiliate.commission_warung_percent', 1.5),
            'prioritas' => (float) config('marketplace.affiliate.commission_prioritas_percent', 2.5),
            'basis' => (string) config('marketplace.affiliate.commission_basis', 'subtotal'),
            'margin_share_min' => (float) config('marketplace.affiliate.commission_margin_share_min', 0.5),
            'margin_share_max' => (float) config('marketplace.affiliate.commission_margin_share_max', 1.5),
        ];
    }

    /**
     * Persentase bagi hasil dari margin bersih (0.5%–1.5% default), naik seiring rasio margin/subtotal.
     */
    public function marginSharePercentForOrder(int $subtotal, int $netMargin): float
    {
        $settings = $this->all();
        $min = $settings['margin_share_min'];
        $max = $settings['margin_share_max'];

        if ($netMargin <= 0 || $subtotal <= 0) {
            return $min;
        }

        $ratio = min(1.0, max(0.0, $netMargin / $subtotal));

        return round($min + ($max - $min) * $ratio, 2);
    }

    public function updateMarginShare(float $min, float $max): void
    {
        $min = max(0, min(100, $min));
        $max = max($min, min(100, $max));

        DB::table('affiliate_settings')->updateOrInsert(
            ['key' => 'commission_margin_share_min'],
            ['value' => (string) $min, 'updated_at' => now(), 'created_at' => now()]
        );
        DB::table('affiliate_settings')->updateOrInsert(
            ['key' => 'commission_margin_share_max'],
            ['value' => (string) $max, 'updated_at' => now(), 'created_at' => now()]
        );

        Cache::forget(self::CACHE_KEY);
    }

    public function percentForPartnerKind(?string $kind): float
    {
        $settings = $this->all();

        return match ($kind) {
            'prioritas' => $settings['prioritas'],
            'warung' => $settings['warung'],
            default => 0.0,
        };
    }

    /** @param  array{warung?: float, prioritas?: float, basis?: string}  $input */
    public function update(array $input): void
    {
        $map = [
            'warung' => 'commission_warung_percent',
            'prioritas' => 'commission_prioritas_percent',
            'basis' => 'commission_basis',
        ];

        foreach ($map as $key => $dbKey) {
            if (! array_key_exists($key, $input)) {
                continue;
            }

            $value = $key === 'basis'
                ? (in_array($input[$key], ['subtotal', 'grand_total'], true) ? $input[$key] : 'subtotal')
                : (string) max(0, min(100, (float) $input[$key]));

            DB::table('affiliate_settings')->updateOrInsert(
                ['key' => $dbKey],
                ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        Cache::forget(self::CACHE_KEY);
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
