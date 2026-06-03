<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class FulfillmentService
{
    /**
     * @param  array<int, array{barang_kode: string, qty: int}>  $cartLines
     * @return array{cabang_id: int, label: string, distance_km: ?float, reason: string}
     */
    public function resolve(?float $lat, ?float $lng, array $cartLines): array
    {
        $branches = config('marketplace.branches');
        $nearbyIds = config('marketplace.nearby_branch_ids', [1, 5]);
        $gudang = $branches[0] ?? null;

        $candidates = [];

        foreach ($nearbyIds as $cabangId) {
            $branch = $branches[$cabangId] ?? null;
            if (! $branch) {
                continue;
            }

            $distance = ($lat !== null && $lng !== null)
                ? $this->haversineKm($lat, $lng, $branch['lat'], $branch['lng'])
                : null;

            if ($distance !== null && $distance > $branch['radius_km']) {
                continue;
            }

            if ($this->cabangCanFulfill((int) $cabangId, $cartLines)) {
                $candidates[] = [
                    'cabang_id' => (int) $cabangId,
                    'label' => $branch['name'],
                    'distance_km' => $distance,
                    'priority' => $branch['priority'],
                    'reason' => 'cabang_dekat',
                ];
            }
        }

        if ($candidates !== []) {
            usort($candidates, function ($a, $b) {
                if ($a['distance_km'] === null && $b['distance_km'] === null) {
                    return $a['priority'] <=> $b['priority'];
                }
                if ($a['distance_km'] === null) {
                    return 1;
                }
                if ($b['distance_km'] === null) {
                    return -1;
                }

                return $a['distance_km'] <=> $b['distance_km'] ?: $a['priority'] <=> $b['priority'];
            });

            $best = $candidates[0];

            return [
                'cabang_id' => $best['cabang_id'],
                'label' => $best['label'],
                'distance_km' => $best['distance_km'],
                'reason' => $best['reason'],
            ];
        }

        $gudangId = 0;

        return [
            'cabang_id' => $gudangId,
            'label' => $gudang['name'] ?? 'Gudang Nugrasir',
            'distance_km' => null,
            'reason' => 'fallback_gudang',
        ];
    }

    /**
     * @param  array<int, array{barang_kode: string, qty: int}>  $cartLines
     */
    public function cabangCanFulfill(int $cabangId, array $cartLines): bool
    {
        foreach ($cartLines as $line) {
            $stock = $this->stockPcsForKode($line['barang_kode'], $cabangId);
            $need = (int) $line['qty'] * (int) ($line['konversi_isi'] ?? 1);
            if ($stock < $need) {
                return false;
            }
        }

        return true;
    }

    public function stockPcsForKode(string $kode, int $cabangId): float
    {
        $row = DB::connection('numart')
            ->table('barang')
            ->where('barang_kode', $kode)
            ->where('barang_cabang', $cabangId)
            ->where('barang_status', '1')
            ->selectRaw('CAST(barang_stock AS DECIMAL(12,2)) as stock')
            ->first();

        return $row ? (float) $row->stock : 0.0;
    }

    public function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return round($earth * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }
}
