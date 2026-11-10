<?php

namespace App\Services;

use App\Models\AffiliateReferral;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AffiliateRegistrationService
{
    public function __construct(
        protected NumartCustomerService $customers,
    ) {}

    public function promoteToKader(User $user): User
    {
        if ($user->affiliate_kader_id) {
            throw new \InvalidArgumentException('Member ini terdaftar di bawah mitra kader lain.');
        }

        $code = $user->affiliate_code ?: $this->generateAffiliateCode();

        $user->update([
            'marketplace_role' => User::ROLE_AFFILIATE_KADER,
            'affiliate_code' => $code,
            'affiliate_active' => true,
            'partner_kind' => null,
        ]);

        return $user->fresh();
    }

    public function deactivateKader(User $user): User
    {
        $user->update(['affiliate_active' => false]);

        return $user->fresh();
    }

    public function activateKader(User $user): User
    {
        if ($user->marketplace_role !== User::ROLE_AFFILIATE_KADER) {
            throw new \InvalidArgumentException('User bukan mitra kader.');
        }

        $user->update(['affiliate_active' => true]);

        return $user->fresh();
    }

    /**
     * @param  array{name: string, phone: string, address?: string, warung_name?: string, partner_kind: string}  $input
     */
    public function registerMitra(User $kader, array $input): AffiliateReferral
    {
        if (! $kader->isAffiliateKader() || ! $kader->affiliate_active) {
            throw new \InvalidArgumentException('Akun mitra kader tidak aktif.');
        }

        $kind = $input['partner_kind'];
        if (! in_array($kind, ['warung', 'prioritas'], true)) {
            throw new \InvalidArgumentException('Jenis mitra tidak valid.');
        }

        $tier = $kind === 'prioritas' ? 2 : 1;

        return DB::transaction(function () use ($kader, $input, $kind, $tier) {
            $existingReferral = AffiliateReferral::where('customer_phone', $this->customers->normalizePhone($input['phone']))->first();
            if ($existingReferral && $existingReferral->affiliate_user_id !== $kader->id) {
                throw new \InvalidArgumentException('Nomor HP sudah terdaftar oleh mitra kader lain.');
            }

            $customer = $this->customers->findByPhone($input['phone']);
            if (! $customer) {
                $customer = $this->customers->createCustomerInNumart(
                    $input['name'],
                    $input['phone'],
                    $input['address'] ?? null,
                );
            }

            $kartu = strtoupper(trim((string) ($customer->customer_kartu ?? '')));
            if ($kartu === '') {
                throw new \RuntimeException('Nomor kartu member gagal dibuat.');
            }

            $existingUser = $this->customers->userForCustomer((int) $customer->customer_id);
            if ($existingUser && $existingUser->affiliate_kader_id && $existingUser->affiliate_kader_id !== $kader->id) {
                throw new \InvalidArgumentException('Member sudah terhubung ke mitra kader lain.');
            }

            // POS: nama, alamat, tipe harga ditulis ke `customer`. ERP: hanya nama & alamat — tipe harga member
            // diatur toko di Backoffice ERP (API mitra tidak boleh mengubahnya), jadi tier mitra di sini hanya lokal
            // sampai toko menyetel tipe yang sama di ERP (lihat NumartOrderSyncService: tier beda → nota tanpa pelanggan).
            $this->customers->updateBasic((int) $customer->customer_id, [
                'nama' => $input['name'],
                'alamat' => $input['address'] ?? $customer->customer_alamat,
                'kategori' => (string) $tier,
            ]);

            $user = $existingUser ?? $this->customers->loginOrCreateUserFromCustomer($customer);
            $user->fill([
                'affiliate_kader_id' => $kader->id,
                'partner_kind' => $kind,
                'price_tier' => $tier,
                'warung_name' => $input['warung_name'] ?? $user->warung_name,
                'marketplace_role' => User::ROLE_MEMBER,
            ]);
            $user->save();

            $referral = AffiliateReferral::updateOrCreate(
                ['member_card' => $kartu],
                [
                    'affiliate_user_id' => $kader->id,
                    'referred_user_id' => $user->id,
                    User::customerIdColumn() => (int) $customer->customer_id,
                    'partner_kind' => $kind,
                    'customer_name' => $input['name'],
                    'customer_phone' => $this->customers->normalizePhone($input['phone']),
                    'warung_name' => $input['warung_name'] ?? null,
                ]
            );

            return $referral;
        });
    }

    public function syncUserFromReferral(User $user): User
    {
        $kartu = strtoupper(trim((string) ($user->member_card ?? '')));
        if ($kartu === '') {
            return $user;
        }

        $referral = AffiliateReferral::where('member_card', $kartu)->first();
        if (! $referral) {
            return $user;
        }

        $tier = $referral->partner_kind === 'prioritas' ? 2 : 1;

        $user->fill([
            'affiliate_kader_id' => $referral->affiliate_user_id,
            'partner_kind' => $referral->partner_kind,
            'price_tier' => $tier,
        ]);
        $user->save();

        if (! $referral->referred_user_id) {
            $referral->update(['referred_user_id' => $user->id]);
        }

        return $user->fresh();
    }

    public function applyReferralCode(User $user, string $code): User
    {
        $code = strtoupper(trim($code));

        if ($code === '') {
            throw new \InvalidArgumentException('Kode referral wajib diisi.');
        }

        if ($user->isAffiliateKader()) {
            throw new \InvalidArgumentException('Mitra kader tidak dapat memakai kode referral.');
        }

        if ($user->affiliate_kader_id) {
            throw new \InvalidArgumentException('Akun sudah terhubung ke mitra kader.');
        }

        $kader = User::query()
            ->where('affiliate_code', $code)
            ->where('marketplace_role', User::ROLE_AFFILIATE_KADER)
            ->where('affiliate_active', true)
            ->first();

        if (! $kader) {
            throw new \InvalidArgumentException('Kode referral tidak valid atau mitra kader nonaktif.');
        }

        $user->update([
            'affiliate_kader_id' => $kader->id,
            'partner_kind' => $user->partner_kind ?: 'referral',
        ]);

        $user = $user->fresh();
        $this->syncReferralRowForUser($user, $kader);

        return $user;
    }

    public function syncReferralRowForUser(User $member, User $kader): void
    {
        if ((int) $member->affiliate_kader_id !== (int) $kader->id) {
            return;
        }

        $kartu = strtoupper(trim((string) ($member->member_card ?? '')));
        if ($kartu === '') {
            return;
        }

        AffiliateReferral::updateOrCreate(
            ['member_card' => $kartu],
            [
                'affiliate_user_id' => $kader->id,
                'referred_user_id' => $member->id,
                User::customerIdColumn() => $member->numartCustomerId(),
                'partner_kind' => $member->partner_kind ?? 'referral',
                'customer_name' => $member->name,
                'customer_phone' => $member->phone,
                'warung_name' => $member->warung_name,
            ]
        );
    }

    protected function generateAffiliateCode(): string
    {
        do {
            $code = 'KDR-'.strtoupper(Str::random(6));
        } while (User::where('affiliate_code', $code)->exists());

        return $code;
    }
}
