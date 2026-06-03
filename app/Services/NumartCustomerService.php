<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NumartCustomerService
{
    /** ID khusus sistem — bukan pelanggan login */
    public const RESERVED_IDS = [0, 1];

    public function normalizePhone(string $phone): string
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

    /**
     * Semua customer aktif dengan nomor yang cocok (semua cabang).
     *
     * @return Collection<int, object>
     */
    public function findCandidatesByPhone(string $phone): Collection
    {
        $normalized = $this->normalizePhone($phone);
        if (strlen($normalized) < 10) {
            return collect();
        }

        $suffix = substr($normalized, -10);
        $like = '%'.$suffix;

        return DB::connection('numart')
            ->table('customer')
            ->whereNotIn('customer_id', self::RESERVED_IDS)
            ->where(function ($q) {
                $this->applyActiveCustomerScope($q);
            })
            ->where(function ($q) use ($like, $normalized) {
                $q->whereRaw(
                    "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(customer_tlpn, ' ', ''), '-', ''), '+', ''), '.', ''), '/', '') LIKE ?",
                    [$like]
                );
                $q->orWhere('customer_tlpn', $normalized);
                if (str_starts_with($normalized, '62')) {
                    $local = '0'.substr($normalized, 2);
                    $q->orWhere('customer_tlpn', $local);
                }
            })
            ->orderByDesc('customer_id')
            ->get();
    }

    public function findByPhone(string $phone): ?object
    {
        $candidates = $this->findCandidatesByPhone($phone);

        if ($candidates->isEmpty()) {
            return null;
        }

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        return $this->pickBestCandidate($candidates, $phone);
    }

    protected function pickBestCandidate(Collection $candidates, string $inputPhone): ?object
    {
        $normalized = $this->normalizePhone($inputPhone);

        $exact = $candidates->first(function ($c) use ($normalized) {
            return $this->normalizePhone((string) $c->customer_tlpn) === $normalized;
        });
        if ($exact) {
            return $exact;
        }

        foreach ($candidates as $c) {
            if ($this->userForCustomer((int) $c->customer_id)) {
                return $c;
            }
        }

        return $candidates->first();
    }

    public function findById(int $customerId, bool $requireActive = true): ?object
    {
        if (in_array($customerId, self::RESERVED_IDS, true)) {
            return null;
        }

        $q = DB::connection('numart')
            ->table('customer')
            ->where('customer_id', $customerId);

        if ($requireActive) {
            $q->where(function ($w) {
                $this->applyActiveCustomerScope($w);
            });
        }

        return $q->first();
    }

    /**
     * Customer dianggap aktif jika status kosong, 1, atau tidak nonaktif.
     */
    public function isActiveCustomer(object $customer): bool
    {
        $status = strtolower(trim((string) ($customer->customer_status ?? '1')));

        if ($status === '' || $status === '1') {
            return true;
        }

        if (in_array($status, ['0', 'nonaktif', 'inactive', 'tidak aktif'], true)) {
            return false;
        }

        return true;
    }

    protected function applyActiveCustomerScope($query): void
    {
        $query->where(function ($w) {
            $w->whereIn('customer_status', ['1', 1, 'aktif', 'Aktif', 'active', 'Active'])
                ->orWhereNull('customer_status')
                ->orWhere('customer_status', '');
        });
    }

    public function cabangLabel(int $cabangId): string
    {
        $branches = config('marketplace.branches', []);

        if (isset($branches[$cabangId]['name'])) {
            return (string) $branches[$cabangId]['name'];
        }

        return match ($cabangId) {
            0 => 'Gudang',
            1 => 'Dukun',
            5 => 'Tegalrejo',
            default => 'Cabang '.$cabangId,
        };
    }

    public function priceTierFromCustomer(object $customer): int
    {
        $cat = (int) ($customer->customer_category ?? 0);

        return match ($cat) {
            2 => 2,
            1 => 1,
            default => 0,
        };
    }

    public function warungStatusFromCustomer(object $customer): string
    {
        if ((int) ($customer->customer_category ?? 0) === 2) {
            return 'approved';
        }

        return 'none';
    }

    public function emailForUser(object $customer, ?string $fallback = null): string
    {
        $email = trim((string) ($customer->customer_email ?? ''));

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            if (! User::where('email', $email)->exists()) {
                return $email;
            }
        }

        $generated = 'numart.'.(int) $customer->customer_id.'@belanja.local';

        if (! User::where('email', $generated)->exists()) {
            return $generated;
        }

        return 'numart.'.(int) $customer->customer_id.'.'.time().'@belanja.local';
    }

    public function userForCustomer(int $customerId): ?User
    {
        return User::where('numart_customer_id', $customerId)->first();
    }

    public function userForPhone(string $phone): ?User
    {
        $normalized = $this->normalizePhone($phone);

        return User::where('phone', $normalized)->first();
    }

    /**
     * Sinkronkan data user Laravel dari master customer Numart.
     */
    public function applyCustomerToUser(User $user, object $customer): User
    {
        $user->fill([
            'numart_customer_id' => (int) $customer->customer_id,
            'name' => $customer->customer_nama,
            'phone' => $this->normalizePhone((string) $customer->customer_tlpn),
            'address' => (string) ($customer->customer_alamat ?? ''),
            'price_tier' => $this->priceTierFromCustomer($customer),
            'warung_verification_status' => $this->warungStatusFromCustomer($customer),
        ]);

        if (! $user->email || str_ends_with($user->email, '@belanja.local')) {
            $user->email = $this->emailForUser($customer, $user->email);
        }

        $user->save();

        return $user;
    }

    /**
     * Daftarkan customer baru langsung ke tabel customer Numart.
     */
    public function createCustomerInNumart(string $name, string $phone, ?string $address = null, ?string $email = null): object
    {
        $normalized = $this->normalizePhone($phone);
        $now = now('Asia/Jakarta')->format('Y-m-d H:i:s');

        $customerId = DB::connection('numart')->table('customer')->insertGetId([
            'customer_nama' => $name,
            'customer_kartu' => '',
            'customer_tlpn' => $normalized,
            'customer_email' => $email ?? '',
            'customer_alamat' => $address ?? '',
            'customer_create' => $now,
            'customer_status' => '1',
            'customer_category' => '1',
            'customer_cabang' => '0',
            'customer_poin' => '0',
        ]);

        $kartu = $this->generateCustomerKartu((int) $customerId);
        DB::connection('numart')
            ->table('customer')
            ->where('customer_id', $customerId)
            ->update(['customer_kartu' => $kartu]);

        $customer = $this->findById((int) $customerId);

        if (! $customer) {
            throw new \RuntimeException('Gagal memuat customer baru dari Numart.');
        }

        return $customer;
    }

    public function generateCustomerKartu(int $customerId): string
    {
        return 'NUBLJ'.str_pad((string) $customerId, 8, '0', STR_PAD_LEFT);
    }

    /**
     * Riwayat belanja dari invoice Numart (semua cabang, by customer_id).
     *
     * @return Collection<int, object>
     */
    public function purchaseHistory(int $customerId, int $limit = 30): Collection
    {
        return DB::connection('numart')
            ->table('invoice')
            ->where('invoice_customer', (string) $customerId)
            ->where('status', '>=', 1)
            ->orderByDesc('invoice_id')
            ->limit($limit)
            ->get([
                'invoice_id',
                'penjualan_invoice',
                'invoice_tgl',
                'invoice_sub_total',
                'invoice_marketplace',
                'invoice_cabang',
                'status',
            ]);
    }

    /**
     * Poin customer — sama dengan POS (customer-zoom.php):
     * floor(SUM(invoice_total) / 100.000) per customer_id.
     */
    public function customerPoints(int $customerId): int
    {
        $divisor = (int) config('marketplace.points_per_amount', 100_000);
        if ($divisor < 1) {
            $divisor = 100_000;
        }

        $total = DB::connection('numart')
            ->table('invoice')
            ->where('invoice_customer', (string) $customerId)
            ->sum('invoice_total');

        return (int) floor((float) $total / $divisor);
    }
}
