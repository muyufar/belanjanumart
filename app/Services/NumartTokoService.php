<?php

namespace App\Services;

use App\Services\Erp\ErpBranchMap;
use App\Support\NumartSource;
use Illuminate\Support\Facades\DB;

class NumartTokoService
{
    public function tokoByCabang(int $cabangId): ?object
    {
        if ($cabangId < 0) {
            return null;
        }

        if (NumartSource::erp()) {
            // O-3: profil cabang ERP (GET /integrasi/cabang) dalam bentuk baris `toko` POS lama; qris = URL penuh.
            try {
                $b = app(ErpBranchMap::class)->profile($cabangId);
            } catch (\Throwable $e) {
                report($e);

                return null;
            }

            return $b === null ? null : (object) [
                'toko_cabang' => $cabangId,
                'toko_nama' => $b['nama'] ?? null,
                'toko_wa' => $b['whatsapp'] ?? null,
                'toko_tlpn' => $b['telepon'] ?? null,
                'toko_alamat' => $b['alamat'] ?? null,
                'toko_kota' => $b['kota'] ?? null,
                'toko_qris' => $b['qris'] ?? null,
            ];
        }

        return DB::connection('numart')
            ->table('toko')
            ->where('toko_cabang', $cabangId)
            ->orderByDesc('toko_id')
            ->first();
    }

    public function branchLabel(int $cabangId): string
    {
        $toko = $this->tokoByCabang($cabangId);
        $nama = trim((string) ($toko->toko_nama ?? ''));

        if ($nama !== '') {
            return $nama;
        }

        return app(NumartCustomerService::class)->cabangLabel($cabangId);
    }

    public function whatsAppForCabang(int $cabangId): string
    {
        $toko = $this->tokoByCabang($cabangId);

        if ($toko) {
            foreach (['toko_wa', 'toko_tlpn'] as $field) {
                $phone = $this->normalizeWhatsApp((string) ($toko->{$field} ?? ''));
                if ($phone !== '') {
                    return $phone;
                }
            }
        }

        $branches = config('marketplace.branches', []);
        $fromConfig = trim((string) ($branches[$cabangId]['wa_phone'] ?? ''));

        if ($fromConfig !== '') {
            return $this->normalizeWhatsApp($fromConfig);
        }

        return $this->normalizeWhatsApp((string) config('marketplace.default_branch_wa', ''));
    }

    public function qrisUrlForCabang(int $cabangId): ?string
    {
        $toko = $this->tokoByCabang($cabangId);
        $qris = trim((string) ($toko->toko_qris ?? ''));

        if ($qris === '') {
            $branches = config('marketplace.branches', []);
            $qris = trim((string) ($branches[$cabangId]['qris_image'] ?? ''));
        }

        if ($qris === '') {
            return null;
        }

        if (str_starts_with($qris, 'http://') || str_starts_with($qris, 'https://')) {
            return $qris;
        }

        $base = rtrim((string) config('marketplace.numart_asset_url'), '/');

        return $base.'/'.ltrim($qris, '/');
    }

    protected function normalizeWhatsApp(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '62')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        if (str_starts_with($digits, '8')) {
            return '62'.$digits;
        }

        return $digits;
    }
}
