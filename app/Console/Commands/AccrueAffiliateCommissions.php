<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\AffiliateCommissionService;
use Illuminate\Console\Command;

class AccrueAffiliateCommissions extends Command
{
    protected $signature = 'affiliate:accrue-pending {--limit=200}';

    protected $description = 'Hitung komisi affiliate untuk pesanan lunas yang belum tercatat (mis. sync POS langsung ke DB)';

    public function handle(AffiliateCommissionService $service): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $orders = Order::query()
            ->whereIn('status', ['processing', 'paid'])
            ->whereDoesntHave('affiliateCommission')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $count = 0;
        foreach ($orders as $order) {
            if ($service->accrueForOrder($order)) {
                $count++;
            }
        }

        $this->info("Komisi baru tercatat: {$count} dari {$orders->count()} pesanan diperiksa.");

        return self::SUCCESS;
    }
}
