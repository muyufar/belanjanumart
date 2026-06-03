<?php

namespace App\View\Composers;

use App\Services\CartSessionService;
use App\Services\PricingService;
use Illuminate\View\View;

class MarketplaceComposer
{
    public function __construct(
        protected CartSessionService $cart,
        protected PricingService $pricing,
    ) {}

    public function compose(View $view): void
    {
        $view->with('cartCount', $this->cart->count());

        if (! $view->offsetExists('tierLabel') && auth()->check()) {
            $tier = $this->pricing->tierForUser(auth()->user());
            $view->with('tierLabel', $this->pricing->tierLabel($tier));
        }
    }
}
