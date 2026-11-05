<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\AffiliateCommissionService;

class OrderObserver
{
    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        if (! in_array($order->status, ['processing', 'paid'], true)) {
            return;
        }

        app(AffiliateCommissionService::class)->accrueForOrder($order);
    }
}
