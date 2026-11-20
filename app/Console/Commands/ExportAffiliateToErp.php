<?php

namespace App\Console\Commands;

use App\Models\AffiliateReferral;
use App\Models\User;
use App\Services\AffiliateSettingsService;
use Illuminate\Console\Command;

/**
 * Pindah mitra kader ke NUMART ERP (2026-11-20): tulis JSON untuk `php artisan kader:impor-belanja <file>` di ERP.
 * Isi: tarif komisi, kader (kartu, HP, kode referral), mitra binaan (dari akun & affiliate_referrals). Komisi lama tidak ikut — lunasi di sini.
 */
class ExportAffiliateToErp extends Command
{
    protected $signature = 'affiliate:ekspor-erp {file=storage/app/kader-ke-erp.json : berkas tujuan}';

    protected $description = 'Ekspor kader, mitra binaan & tarif komisi untuk diimpor ke NUMART ERP';

    public function handle(AffiliateSettingsService $settings): int
    {
        $s = $settings->all();
        $kaders = User::where('marketplace_role', User::ROLE_AFFILIATE_KADER)->orderBy('id')->get();
        $cardOf = $kaders->mapWithKeys(fn (User $k) => [$k->id => strtoupper(trim((string) $k->member_card))]);

        $mitra = [];
        foreach (User::whereNotNull('affiliate_kader_id')->orderBy('id')->get() as $u) {
            $mitra[strtoupper(trim((string) $u->member_card)) ?: 'u'.$u->id] = ['pelanggan_id' => $u->erp_customer_id, 'kartu' => $u->member_card, 'hp' => $u->phone,
                'nama' => $u->name, 'kader_kartu' => $cardOf[$u->affiliate_kader_id] ?? null, 'jenis' => $u->partner_kind ?: 'referral', 'nama_warung' => $u->warung_name];
        }
        foreach (AffiliateReferral::orderBy('id')->get() as $r) {   // mitra yang didaftarkan kader tetapi belum pernah login
            $key = strtoupper(trim((string) $r->member_card));
            if ($key === '' || isset($mitra[$key])) {
                continue;
            }
            $mitra[$key] = ['pelanggan_id' => $r->erp_customer_id, 'kartu' => $r->member_card, 'hp' => $r->customer_phone, 'nama' => $r->customer_name,
                'kader_kartu' => $cardOf[$r->affiliate_user_id] ?? null, 'jenis' => $r->partner_kind ?: 'referral', 'nama_warung' => $r->warung_name];
        }

        $data = [
            'dibuat' => now()->toIso8601String(),
            'tarif' => ['warung' => $s['warung'], 'prioritas' => $s['prioritas'], 'referral' => $s['referral'], 'dasar' => $s['basis']],
            'kader' => $kaders->map(fn (User $k) => ['pelanggan_id' => $k->erp_customer_id, 'kartu' => $k->member_card, 'hp' => $k->phone, 'nama' => $k->name,
                'kode' => $k->affiliate_code, 'aktif' => (bool) $k->affiliate_active])->values()->all(),
            'mitra' => array_values($mitra),
        ];

        $path = $this->argument('file');
        $full = str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path) ? $path : base_path($path);
        file_put_contents($full, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->info(count($data['kader']).' kader, '.count($data['mitra']).' mitra → '.$full);
        $this->line('Di server ERP: php artisan kader:impor-belanja '.basename($full).' --uji   (lalu tanpa --uji)');

        return self::SUCCESS;
    }
}
