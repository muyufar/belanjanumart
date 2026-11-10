<?php

namespace App\Services;

use App\Models\User;
use App\Services\Erp\ErpCustomers;
use App\Support\NumartSource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class NumartCustomerService
{
    /** ID khusus sistem — bukan pelanggan login */
    public const RESERVED_IDS = [0, 1];

    protected function erp(): ErpCustomers
    {
        return app(ErpCustomers::class);
    }

    /** Perbarui nama/email/alamat member di sistem toko aktif (ERP: PATCH; tipe harga & toko asal diatur toko). */
    public function updateBasic(int $customerId, array $data): void
    {
        if (NumartSource::erp()) {
            $this->erp()->update($customerId, array_intersect_key($data, array_flip(['nama', 'email', 'alamat'])));

            return;
        }

        $map = ['nama' => 'customer_nama', 'email' => 'customer_email', 'alamat' => 'customer_alamat', 'kategori' => 'customer_category'];
        $update = [];
        foreach ($data as $k => $v) {
            if (isset($map[$k]) && $v !== null) {
                $update[$map[$k]] = $v;
            }
        }
        if ($update !== []) {
            DB::connection('numart')->table('customer')->where('customer_id', $customerId)->update($update);
        }
    }

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

        if (NumartSource::erp()) {
            return $this->erp()->findByPhone($normalized)->filter(fn ($c) => $this->isActiveCustomer($c))->values();
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
        if (NumartSource::erp()) {
            if ($customerId < 1) {
                return null;
            }
            $c = $this->erp()->find($customerId);

            return $c && (! $requireActive || $this->isActiveCustomer($c)) ? $c : null;
        }

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

    public function verificationStatusFromCustomer(object $customer): string
    {
        $status = strtolower(trim((string) ($customer->customer_verifikasi_status ?? 'none')));

        if (in_array($status, ['none', 'pending', 'approved', 'rejected'], true)) {
            return $status;
        }

        return 'none';
    }

    public function warungStatusFromCustomer(object $customer): string
    {
        return $this->verificationStatusFromCustomer($customer);
    }

    /**
     * Sinkronkan upload verifikasi member ke master customer Numart.
     */
    public function syncVerificationToCustomer(
        int $customerId,
        ?string $ktpPath,
        ?string $businessPhotoPath,
        string $status = 'pending',
    ): void {
        if ($customerId < 1) {
            return;
        }

        if (NumartSource::erp()) {
            // O-2: berkas dikirim ke ERP (base64, ≤ 3 MB per foto), diputuskan di Backoffice ERP → Pelanggan.
            try {
                $disk = Storage::disk('public');
                if ($ktpPath && $disk->exists($ktpPath)) {
                    $this->erp()->submitVerification(
                        $customerId,
                        $disk->get($ktpPath),
                        $businessPhotoPath && $disk->exists($businessPhotoPath) ? $disk->get($businessPhotoPath) : null,
                    );
                }
            } catch (\Throwable $e) {
                report($e);
            }

            return;
        }

        try {
            DB::connection('numart')
                ->table('customer')
                ->where('customer_id', $customerId)
                ->update([
                    'customer_ktp_path' => $ktpPath,
                    'customer_foto_warung_path' => $businessPhotoPath,
                    'customer_verifikasi_status' => $status,
                    'customer_verifikasi_at' => now('Asia/Jakarta')->format('Y-m-d H:i:s'),
                ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function findByKartu(string $kartu): ?object
    {
        $kartu = strtoupper(trim(preg_replace('/\s+/', '', $kartu) ?? ''));

        if ($kartu === '') {
            return null;
        }

        if (NumartSource::erp()) {
            $c = $this->erp()->findByCard($kartu);

            return $c && $this->isActiveCustomer($c) ? $c : null;
        }

        return DB::connection('numart')
            ->table('customer')
            ->whereNotIn('customer_id', self::RESERVED_IDS)
            ->where(function ($q) {
                $this->applyActiveCustomerScope($q);
            })
            ->whereRaw('UPPER(TRIM(customer_kartu)) = ?', [$kartu])
            ->first();
    }

    public function loginOrCreateUserFromCustomer(object $customer): User
    {
        $user = $this->userForCustomer((int) $customer->customer_id);

        if (! $user) {
            $user = User::create([
                'name' => $customer->customer_nama,
                'email' => $this->emailForUser($customer),
                'password' => bcrypt(str()->random(32)),
                'phone' => $this->normalizePhone((string) $customer->customer_tlpn),
                'must_change_password' => false,
            ]);
        }

        $user->member_card = strtoupper(trim((string) ($customer->customer_kartu ?? '')));
        $user->member_cabang = (int) ($customer->customer_cabang ?? 0);

        return $this->applyCustomerToUser($user, $customer);
    }

    public function emailForUser(object $customer, ?string $fallback = null): string
    {
        $email = trim((string) ($customer->customer_email ?? ''));

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            if (! User::where('email', $email)->exists()) {
                return $email;
            }
        }

        // Id ERP dan id POS lama tumpang-tindih → awalan berbeda agar email buatan tidak bentrok.
        $prefix = NumartSource::erp() ? 'erp.' : 'numart.';
        $generated = $prefix.(int) $customer->customer_id.'@belanja.local';

        if (! User::where('email', $generated)->exists()) {
            return $generated;
        }

        return $prefix.(int) $customer->customer_id.'.'.time().'@belanja.local';
    }

    public function userForCustomer(int $customerId): ?User
    {
        $user = User::where(User::customerIdColumn(), $customerId)->first();
        if ($user || ! NumartSource::erp()) {
            return $user;
        }

        // Akun dari era POS lama belum punya erp_customer_id → tautkan sekali (kartu, lalu HP) agar tidak dibuatkan akun ganda.
        $customer = $this->erp()->find($customerId);

        return $customer ? $this->linkLegacyUser($customer) : null;
    }

    /**
     * Tautkan akun lama (numart_customer_id = id POS) ke pelanggan ERP: cocok nomor kartu, atau — bila kartu kosong —
     * satu-satunya akun dengan HP yang sama. Ambigu → tidak ditautkan (akan dibuat akun baru saat login, seperti member baru).
     */
    public function linkLegacyUser(object $customer): ?User
    {
        $erpId = (int) $customer->customer_id;
        $unlinked = fn () => User::whereNull('erp_customer_id');
        $kartu = strtoupper(trim((string) ($customer->customer_kartu ?? '')));
        $user = $kartu !== '' ? $unlinked()->whereRaw('UPPER(TRIM(member_card)) = ?', [$kartu])->first() : null;

        if (! $user) {
            $phone = $this->normalizePhone((string) ($customer->customer_tlpn ?? ''));
            $byPhone = strlen($phone) >= 10 ? $unlinked()->where('phone', $phone)->limit(2)->get() : collect();
            $candidate = $byPhone->count() === 1 ? $byPhone->first() : null;
            $candidateCard = strtoupper(trim((string) ($candidate->member_card ?? '')));
            $user = $candidate && ($candidateCard === '' || $candidateCard === $kartu) ? $candidate : null;
        }

        if ($user) {
            $user->forceFill(['erp_customer_id' => $erpId])->save();
        }

        return $user;
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
        $verificationStatus = $this->verificationStatusFromCustomer($customer);

        $user->fill([
            User::customerIdColumn() => (int) $customer->customer_id,
            'name' => $customer->customer_nama,
            'phone' => $this->normalizePhone((string) $customer->customer_tlpn),
            'address' => (string) ($customer->customer_alamat ?? ''),
            'price_tier' => $this->priceTierFromCustomer($customer),
            'member_card' => strtoupper(trim((string) ($customer->customer_kartu ?? ''))),
            'member_cabang' => (int) ($customer->customer_cabang ?? 0),
            'member_verification_status' => $verificationStatus,
            'ktp_path' => $customer->customer_ktp_path ?? $user->ktp_path,
            'business_photo_path' => $customer->customer_foto_warung_path ?? $user->business_photo_path,
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

        if (NumartSource::erp()) {
            // O-2: ERP membuat member retail, toko asal Gudang, kartu NUBLJ + id 8 digit (sama dengan aturan lama).
            return $this->erp()->register($name, $normalized, $email, $address);
        }

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
        if (NumartSource::erp()) {
            // Nota ERP saja; riwayat POS lama tidak dimigrasi (totalnya tetap ikut di poin).
            return $this->erp()->history($customerId, $limit);
        }

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
        if (NumartSource::erp()) {
            // O-2: poin dihitung ERP = floor((Σ total POS lama + Σ total ERP) ÷ 100.000)
            return $this->erp()->points($customerId);
        }

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
