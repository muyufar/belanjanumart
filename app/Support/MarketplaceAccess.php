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

    public static function isSuperAdmin(?User $user): bool
    {
        if (! $user?->email) {
            return false;
        }

        $allowed = self::superAdminEmails();

        return $allowed !== [] && in_array($user->email, $allowed, true);
    }

    public static function isAdmin(?User $user): bool
    {
        if (! $user?->email) {
            return false;
        }

        if (self::isSuperAdmin($user)) {
            return true;
        }

        $allowed = self::adminEmails();

        return $allowed !== [] && in_array($user->email, $allowed, true);
    }
}
