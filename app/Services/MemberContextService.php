<?php

namespace App\Services;

use App\Models\User;
use App\Services\Erp\ErpBranchMap;
use App\Support\NumartSource;

class MemberContextService
{
    public function __construct(
        protected NumartCustomerService $customers,
        protected NumartTokoService $toko,
        protected MarketplaceSettingsService $settings,
    ) {}

    public function memberCabangId(?User $user): int
    {
        $cabang = $this->rawMemberCabangId($user);

        // ERP: hanya cabang dalam cakupan klien integrasi yang bisa dipakai untuk harga/stok/reservasi.
        return NumartSource::erp() ? app(ErpBranchMap::class)->servable($cabang) : $cabang;
    }

    protected function rawMemberCabangId(?User $user): int
    {
        if (! $user) {
            return (int) config('marketplace.catalog_cabang_display', 0);
        }

        if ($user->member_cabang !== null) {
            return (int) $user->member_cabang;
        }

        $customer = $this->customerForUser($user);
        if (! $customer) {
            return (int) config('marketplace.catalog_cabang_display', 0);
        }

        return (int) ($customer->customer_cabang ?? 0);
    }

    public function customerForUser(?User $user): ?object
    {
        if (! $user?->numartCustomerId()) {
            return null;
        }

        return $this->customers->findById((int) $user->numartCustomerId());
    }

    public function minOrderAmount(int $tier): int
    {
        return $this->settings->minOrderForTier($tier);
    }

    public function verificationStatusForUser(?User $user): string
    {
        if (! $user) {
            return 'none';
        }

        $customer = $this->customerForUser($user);
        if ($customer) {
            return $this->customers->verificationStatusFromCustomer($customer);
        }

        return (string) ($user->member_verification_status ?? 'none');
    }

    public function canUseCod(?User $user): bool
    {
        return $this->verificationStatusForUser($user) === 'approved';
    }

    public function needsVerificationUpload(User $user): bool
    {
        return ! in_array($this->verificationStatusForUser($user), ['pending', 'approved'], true);
    }

    public function isGrosirMember(User $user): bool
    {
        return (int) $user->price_tier === 2;
    }

    public function branchWhatsApp(int $cabangId): string
    {
        return $this->toko->whatsAppForCabang($cabangId);
    }

    public function branchQrisUrl(int $cabangId): ?string
    {
        return $this->toko->qrisUrlForCabang($cabangId);
    }

    public function branchLabel(int $cabangId): string
    {
        return $this->toko->branchLabel($cabangId);
    }
}
