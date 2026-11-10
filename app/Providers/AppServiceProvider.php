<?php

namespace App\Providers;

use App\Models\Order;
use App\Observers\OrderObserver;
use App\View\Composers\MarketplaceComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Peta cabang POS→ERP dihitung sekali per request (NUMART_SOURCE=erp).
        $this->app->singleton(\App\Services\Erp\ErpBranchMap::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Order::observe(OrderObserver::class);

        View::composer(['layouts.app', 'layouts.partials.bottom-nav', 'layouts.partials.desktop-sidebar', 'layouts.partials.app-footer'], MarketplaceComposer::class);
    }
}
