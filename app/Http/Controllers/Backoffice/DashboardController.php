<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\AffiliateCommission;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('backoffice.dashboard', [
            'kaderCount' => User::where('marketplace_role', User::ROLE_AFFILIATE_KADER)->count(),
            'referralCount' => User::whereNotNull('affiliate_kader_id')->count(),
            'commissionPending' => (int) AffiliateCommission::where('status', AffiliateCommission::STATUS_ACCRUED)->sum('commission_amount'),
        ]);
    }
}
