<?php

namespace App\Services\Erp;

use App\Services\NumartErpClient;
use Illuminate\Support\Facades\Cache;

/**
 * Seluruh aplikasi ini tetap memakai nomor cabang POS lama (0=GDG, 1=DKN, 2=PKS, 3=SRB, 5=TGR, 6=RTR) — di config
 * marketplace, orders.fulfillment_cabang, users.member_cabang. Id cabang ERP berbeda; kelas ini menerjemahkan di batas API.
 * Sumber: services.numart_erp.branch_map (eksplisit) → kalau kosong, cocokkan kode cabang dengan GET /integrasi/cabang.
 */
class ErpBranchMap
{
    /** @var array<int, int>|null */
    private ?array $map = null;

    public function __construct(private NumartErpClient $erp) {}

    public function toErp(int $posCabang): int
    {
        $map = $this->map();
        if (! isset($map[$posCabang])) {
            throw new \RuntimeException("Cabang POS {$posCabang} belum dipetakan ke cabang ERP (cek NUMART_ERP_BRANCH_MAP / cakupan klien integrasi).");
        }

        return $map[$posCabang];
    }

    /**
     * Cabang POS yang bisa dilayani klien integrasi ini; cabang di luar cakupan klien (mis. member toko asal PKS
     * sementara klien hanya GDG,DKN,TGR) jatuh ke cabang tampilan katalog (marketplace.catalog_cabang_display).
     */
    public function servable(int $posCabang): int
    {
        return isset($this->map()[$posCabang]) ? $posCabang : (int) config('marketplace.catalog_cabang_display', 0);
    }

    /** @param list<int> $posCabangs @return list<int> cabang yang tidak dipetakan dilewati */
    public function toErpList(array $posCabangs): array
    {
        $map = $this->map();

        return array_values(array_unique(array_filter(array_map(fn ($c) => $map[(int) $c] ?? null, $posCabangs), fn ($v) => $v !== null)));
    }

    public function toPos(?int $erpId, int $default = 0): int
    {
        if ($erpId === null) {
            return $default;
        }
        $pos = array_search($erpId, $this->map(), true);

        return $pos === false ? $default : (int) $pos;
    }

    /** Profil cabang ERP (nama, telepon, whatsapp, qris URL — O-3) untuk nomor cabang POS; null bila tidak ada. */
    public function profile(int $posCabang): ?array
    {
        $id = $this->map()[$posCabang] ?? null;
        if ($id === null) {
            return null;
        }
        foreach ($this->branches() as $b) {
            if ((int) $b['cabang_id'] === $id) {
                return $b;
            }
        }

        return null;
    }

    /** @return list<array<string, mixed>> GET /integrasi/cabang (cache 10 menit) */
    public function branches(): array
    {
        return Cache::remember('numart_erp.branches', 600, fn () => $this->erp->branches());
    }

    /** @return array<int, int> nomor POS → id ERP */
    public function map(): array
    {
        if ($this->map !== null) {
            return $this->map;
        }

        $explicit = trim((string) config('services.numart_erp.branch_map', ''));
        if ($explicit !== '') {
            $map = [];
            foreach (explode(',', $explicit) as $pair) {
                [$pos, $erp] = array_pad(array_map('trim', explode(':', $pair)), 2, '');
                if ($pos !== '' && ctype_digit($pos) && ctype_digit($erp)) {
                    $map[(int) $pos] = (int) $erp;
                }
            }

            return $this->map = $map;
        }

        $codes = (array) config('services.numart_erp.branch_codes', []);
        $byCode = [];
        foreach ($this->branches() as $b) {
            $byCode[strtoupper((string) ($b['kode'] ?? ''))] = (int) $b['cabang_id'];
        }
        $map = [];
        foreach ($codes as $pos => $code) {
            if (isset($byCode[strtoupper($code)])) {
                $map[(int) $pos] = $byCode[strtoupper($code)];
            }
        }

        return $this->map = $map;
    }
}
