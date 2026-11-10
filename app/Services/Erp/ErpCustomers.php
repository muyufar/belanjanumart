<?php

namespace App\Services\Erp;

use App\Services\NumartErpClient;
use Illuminate\Support\Collection;

/**
 * Member dari ERP (O-2, /integrasi/pelanggan) dalam bentuk baris tabel `customer` POS lama, supaya
 * NumartCustomerService dan pemanggilnya (login, aktivasi, profil, afiliasi) tidak berubah.
 * customer_id di sini = id pelanggan ERP (≠ customer_id POS lama; disimpan di users.erp_customer_id).
 */
class ErpCustomers
{
    public const TIERS = ['umum' => 0, 'retail' => 1, 'grosir' => 2];

    public function __construct(private NumartErpClient $erp, private ErpBranchMap $branches) {}

    /** @return Collection<int, object> */
    public function findByPhone(string $phone): Collection
    {
        return collect($this->erp->findCustomers(['hp' => $phone]))->map(fn ($c) => $this->toRow($c));
    }

    public function findByCard(string $card): ?object
    {
        $c = $this->erp->findCustomers(['kartu' => $card])[0] ?? null;

        return $c ? $this->toRow($c) : null;
    }

    public function find(int $id): ?object
    {
        try {
            return $this->toRow($this->erp->customer($id));
        } catch (\RuntimeException $e) {
            if ($e->getCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /** Daftar member baru; HP sudah terdaftar (422) → kembalikan member yang ada. */
    public function register(string $name, string $phone, ?string $email, ?string $address): object
    {
        try {
            return $this->toRow($this->erp->registerCustomer($name, $phone, $email ?: null, $address ?: null));
        } catch (\RuntimeException $e) {
            if ($e->getCode() === 422 && ($existing = $this->findByPhone($phone)->first())) {
                return $existing;
            }
            throw $e;
        }
    }

    /** @param array{nama?:string, email?:string, alamat?:string} $data */
    public function update(int $id, array $data): object
    {
        return $this->toRow($this->erp->updateCustomer($id, array_filter($data, fn ($v) => $v !== null && $v !== '')));
    }

    /** @return Collection<int, object> bentuk baris `invoice` POS lama (riwayat nota ERP) */
    public function history(int $id, int $limit = 30): Collection
    {
        $codeToPos = array_flip(array_map('strtoupper', (array) config('services.numart_erp.branch_codes', [])));

        return collect($this->erp->customerHistory($id, $limit))->map(fn ($s) => (object) [
            'invoice_id' => null,
            'penjualan_invoice' => (string) $s['nota'],
            'invoice_tgl' => (string) $s['waktu'],
            'invoice_sub_total' => (float) $s['total'],
            'invoice_marketplace' => ! empty($s['online']) ? (string) $s['nota'] : null,
            'invoice_cabang' => $codeToPos[strtoupper((string) ($s['cabang'] ?? ''))] ?? null,
            'nama_cabang' => $s['nama_cabang'] ?? null,
            'status' => $s['status'] ?? null,
        ]);
    }

    public function points(int $id): int
    {
        return (int) ($this->erp->customer($id)['poin'] ?? 0);
    }

    /** Ajukan verifikasi (KTP + foto warung) — berkas biner dikirim base64, status ERP → pending. */
    public function submitVerification(int $id, string $ktp, ?string $business): object
    {
        return $this->toRow($this->erp->submitVerification($id, $ktp, $business));
    }

    /** @param array<string, mixed> $c pelanggan ERP */
    public function toRow(array $c): object
    {
        return (object) [
            'customer_id' => (int) $c['pelanggan_id'],
            'customer_nama' => (string) ($c['nama'] ?? ''),
            'customer_tlpn' => (string) ($c['hp'] ?? ''),
            'customer_email' => (string) ($c['email'] ?? ''),
            'customer_alamat' => (string) ($c['alamat'] ?? ''),
            'customer_kartu' => (string) ($c['kartu'] ?? ''),
            'customer_category' => (string) (self::TIERS[$c['tipe_harga'] ?? 'umum'] ?? 0),
            'customer_cabang' => $this->branches->toPos(isset($c['cabang_asal_id']) ? (int) $c['cabang_asal_id'] : null, 0),
            'customer_status' => ($c['aktif'] ?? true) ? '1' : '0',
            'customer_poin' => (int) ($c['poin'] ?? 0),
            'customer_verifikasi_status' => (string) ($c['verifikasi']['status'] ?? 'none'),
            'customer_verifikasi_catatan' => $c['verifikasi']['catatan'] ?? null,
            // Foto KTP/warung disimpan privat di ERP; tidak ada path lokal.
            'customer_ktp_path' => null,
            'customer_foto_warung_path' => null,
            'erp' => true,
        ];
    }
}
