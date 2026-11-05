<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AffiliateSettingsService
{
    private const CACHE_KEY = 'affiliate.settings';

    /** @return array{warung: float, prioritas: float, basis: string} */
    public function all(): array
    {
        if (! Schema::hasTable('affiliate_settings')) {
            return $this->defaults();
        }

        return Cache::remember(self::CACHE_KEY, 60, function () {
            $rows = DB::table('affiliate_settings')->pluck('value', 'key');

            return [
                'warung' => (float) ($rows['commission_warung_percent'] ?? $this->defaults()['warung']),
                'prioritas' => (float) ($rows['commission_prioritas_percent'] ?? $this->defaults()['prioritas']),
                'basis' => (string) ($rows['commission_basis'] ?? 'subtotal'),
            ];
        });
    }

    /** @return array{warung: float, prioritas: float, basis: string} */
    public function defaults(): array
    {
        return [
            'warung' => (float) config('marketplace.affiliate.commission_warung_percent', 1.5),
            'prioritas' => (float) config('marketplace.affiliate.commission_prioritas_percent', 2.5),
            'basis' => (string) config('marketplace.affiliate.commission_basis', 'subtotal'),
        ];
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
