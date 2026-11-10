<?php

namespace App\Console\Commands;

use App\Models\AffiliateReferral;
use App\Models\User;
use App\Services\Erp\ErpCustomers;
use App\Services\NumartCustomerService;
use Illuminate\Console\Command;

/**
 * Pemetaan sekali jalan sebelum/ saat cutover: isi users.erp_customer_id untuk akun dari era POS lama.
 * Kunci: nomor kartu member (ERP card_number = customer_kartu POS lama), bila kosong: HP dengan tepat satu member ERP.
 * Tidak mengubah numart_customer_id (id POS lama tetap untuk rollback). Aman diulang.
 */
class LinkErpCustomers extends Command
{
    protected $signature = 'numart:tautkan-pelanggan-erp {--dry-run : Hanya tampilkan hasil, tidak menyimpan} {--limit=0}';

    protected $description = 'Tautkan akun belanjanumart ke id pelanggan NUMART ERP (users.erp_customer_id) lewat kartu/HP';

    public function handle(ErpCustomers $erp, NumartCustomerService $customers): int
    {
        $dry = (bool) $this->option('dry-run');
        $q = User::whereNull('erp_customer_id')
            ->where(fn ($w) => $w->whereNotNull('numart_customer_id')->orWhereNotNull('member_card'))
            ->orderBy('id');
        if (($limit = (int) $this->option('limit')) > 0) {
            $q->limit($limit);
        }

        $stats = ['kartu' => 0, 'hp' => 0, 'tidak_ketemu' => 0, 'ambigu' => 0, 'galat' => 0];
        foreach ($q->get() as $user) {
            try {
                [$match, $how] = $this->match($user, $erp, $customers);
            } catch (\Throwable $e) {
                $stats['galat']++;
                $this->warn("#{$user->id}: {$e->getMessage()}");

                continue;
            }
            if (! $match) {
                $stats[$how]++;

                continue;
            }
            $stats[$how]++;
            if (User::where('erp_customer_id', $match->customer_id)->where('id', '!=', $user->id)->exists()) {
                $stats['ambigu']++;
                $stats[$how]--;
                $this->warn("#{$user->id}: pelanggan ERP {$match->customer_id} sudah tertaut ke akun lain — lewati");

                continue;
            }
            if (! $dry) {
                $user->forceFill(['erp_customer_id' => $match->customer_id])->save();
                AffiliateReferral::where('referred_user_id', $user->id)->update(['erp_customer_id' => $match->customer_id]);
            }
        }

        $this->table(array_keys($stats), [array_values($stats)]);
        $this->info($dry ? 'Dry-run: tidak ada yang disimpan.' : 'Selesai.');

        return self::SUCCESS;
    }

    /** @return array{0: ?object, 1: string} */
    private function match(User $user, ErpCustomers $erp, NumartCustomerService $customers): array
    {
        $card = strtoupper(trim((string) $user->member_card));
        if ($card !== '' && ($c = $erp->findByCard($card))) {
            return [$c, 'kartu'];
        }

        $phone = $customers->normalizePhone((string) $user->phone);
        if (strlen($phone) < 10) {
            return [null, 'tidak_ketemu'];
        }
        $found = $erp->findByPhone($phone);

        return match ($found->count()) {
            0 => [null, 'tidak_ketemu'],
            1 => [$found->first(), 'hp'],
            default => [null, 'ambigu'],
        };
    }
}
