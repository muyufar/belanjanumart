<?php

namespace App\Services;

use App\Services\Erp\ErpSettings;
use App\Support\NumartSource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Baca pengaturan Belanja Online dari tabel Numart `marketplace_settings`
 * (dikelola di POS: marketplace-min-order).
 */
class MarketplaceSettingsService
{
    private const CACHE_KEY = 'marketplace.settings.min_order';

    private const CACHE_SECONDS = 30;

    public function tableExists(): bool
    {
        try {
            return Schema::connection('numart')->hasTable('marketplace_settings');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array{retail:int,grosir:int}
     */
    public function minOrderAmounts(): array
    {
        $defaults = [
            'retail' => (int) config('marketplace.min_order_retail', 500_000),
            'grosir' => (int) config('marketplace.min_order_grosir', 1_000_000),
        ];

        if (NumartSource::erp()) {
            // O-4: diatur dari Backoffice ERP → Pengaturan → Belanja online
            try {
                $rows = app(ErpSettings::class)->all()['pengaturan'] ?? [];

                return [
                    'retail' => isset($rows['min_order_retail']) ? max(0, (int) $rows['min_order_retail']) : $defaults['retail'],
                    'grosir' => isset($rows['min_order_grosir']) ? max(0, (int) $rows['min_order_grosir']) : $defaults['grosir'],
                ];
            } catch (\Throwable $e) {
                report($e);

                return $defaults;
            }
        }

        if (! $this->tableExists()) {
            return $defaults;
        }

        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, function () use ($defaults) {
                $rows = DB::connection('numart')
                    ->table('marketplace_settings')
                    ->whereIn('setting_key', ['min_order_retail', 'min_order_grosir'])
                    ->pluck('setting_value', 'setting_key');

                return [
                    'retail' => isset($rows['min_order_retail'])
                        ? max(0, (int) $rows['min_order_retail'])
                        : $defaults['retail'],
                    'grosir' => isset($rows['min_order_grosir'])
                        ? max(0, (int) $rows['min_order_grosir'])
                        : $defaults['grosir'],
                ];
            });
        } catch (\Throwable) {
            return $defaults;
        }
    }

    public function minOrderForTier(int $tier): int
    {
        $amounts = $this->minOrderAmounts();

        return match ($tier) {
            2 => $amounts['grosir'],
            1 => $amounts['retail'],
            default => 0,
        };
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        app(ErpSettings::class)->forget();
    }
}
