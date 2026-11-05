<?php

namespace App\Support;

use App\Models\User;

class MarketplaceAccess
{
    /** @return list<string> */
    public static function superAdminEmails(): array
    {
        $raw = (string) config('marketplace.super_admin_emails', '');

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    /** @return list<string> */
    public static function adminEmails(): array
    {
        $raw = (string) config('marketplace.admin_emails', '');

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    /** @return list<string> */
    public static function superAdminCards(): array
    {
        $raw = (string) config('marketplace.super_admin_cards', '');

        return array_values(array_filter(array_map(
            static fn (string $c) => strtoupper(trim($c)),
            explode(',', $raw)
        )));
    }

    /** @return list<string> */
    public static function adminCards(): array
    {
        $raw = (string) config('marketplace.admin_cards', '');

        return array_values(array_filter(array_map(
            static fn (string $c) => strtoupper(trim($c)),
            explode(',', $raw)
        )));
    }

    public static function isSuperAdmin(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $emails = self::superAdminEmails();
        if ($emails !== [] && $user->email && in_array($user->email, $emails, true)) {
            return true;
        }

        $cards = self::superAdminCards();
        $kartu = strtoupper(trim((string) ($user->member_card ?? '')));

        return $cards !== [] && $kartu !== '' && in_array($kartu, $cards, true);
    }

    public static function isAdmin(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if (self::isSuperAdmin($user)) {
            return true;
        }

        if ($user->marketplace_role === User::ROLE_BACKOFFICE) {
            return true;
        }

        $emails = self::adminEmails();
        if ($emails !== [] && $user->email && in_array($user->email, $emails, true)) {
            return true;
        }

        $cards = self::adminCards();
        $kartu = strtoupper(trim((string) ($user->member_card ?? '')));

        return $cards !== [] && $kartu !== '' && in_array($kartu, $cards, true);
    }

    /** Staff backoffice (kelola affiliate, komisi, verifikasi). */
    public static function isBackoffice(?User $user): bool
    {
        return self::isAdmin($user);
    }
}
