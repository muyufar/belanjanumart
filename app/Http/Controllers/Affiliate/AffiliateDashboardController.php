<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Models\AffiliateCommission;
use App\Models\Order;
use App\Models\User;
use App\Services\AffiliateCommissionService;
use App\Services\AffiliateRegistrationService;
use App\Services\AffiliateSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AffiliateDashboardController extends Controller
{
    public function __construct(
        protected AffiliateCommissionService $commissions,
        protected AffiliateRegistrationService $registration,
        protected AffiliateSettingsService $settings,
    ) {}

    public function index(Request $request): View
    {
        $kader = $request->user();
        $totals = $this->commissions->totalsForAffiliate($kader->id);

        $recentCommissions = AffiliateCommission::with(['referredUser', 'order'])
            ->where('affiliate_user_id', $kader->id)
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $referrals = User::query()
            ->where('affiliate_kader_id', $kader->id)
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return view('affiliate.dashboard', [
            'kader' => $kader,
            'totals' => $totals,
            'settings' => $this->settings->all(),
            'recentCommissions' => $recentCommissions,
            'referrals' => $referrals,
            'cartCount' => 0,
        ]);
    }

    public function referrals(Request $request): View
    {
        $kader = $request->user();

        $members = User::query()
            ->where('affiliate_kader_id', $kader->id)
            ->orderByDesc('id')
            ->paginate(20);

        return view('affiliate.referrals', [
            'kader' => $kader,
            'members' => $members,
            'cartCount' => 0,
        ]);
    }

    public function orders(Request $request): View
    {
        $kader = $request->user();
        $memberIds = User::where('affiliate_kader_id', $kader->id)->pluck('id');

        $orders = Order::with(['user', 'items'])
            ->whereIn('user_id', $memberIds)
            ->orderByDesc('id')
            ->paginate(20);

        $commissionByOrder = AffiliateCommission::where('affiliate_user_id', $kader->id)
            ->whereIn('order_id', $orders->pluck('id'))
            ->get()
            ->keyBy('order_id');

        return view('affiliate.orders', [
            'kader' => $kader,
            'orders' => $orders,
            'commissionByOrder' => $commissionByOrder,
            'cartCount' => 0,
        ]);
    }

    public function createReferral(Request $request): View
    {
        return view('affiliate.register-mitra', [
            'kader' => $request->user(),
            'cartCount' => 0,
        ]);
    }

    public function storeReferral(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'phone' => 'required|string|max:30',
            'address' => 'nullable|string|max:1000',
            'warung_name' => 'nullable|string|max:200',
            'partner_kind' => 'required|in:warung,prioritas',
        ]);

        try {
            $referral = $this->registration->registerMitra($request->user(), $validated);

            return redirect()
                ->route('affiliate.referrals')
                ->with('success', 'Mitra terdaftar. Nomor kartu: '.$referral->member_card);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }
}
