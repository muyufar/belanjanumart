<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\CartSessionService;
use App\Services\MemberContextService;
use App\Services\NumartCustomerService;
use App\Services\AffiliateRegistrationService;
use App\Services\OrderTrackingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        protected NumartCustomerService $numartCustomers,
        protected MemberContextService $memberContext,
        protected CartSessionService $cart,
        protected OrderTrackingService $tracking,
        protected AffiliateRegistrationService $affiliates,
    ) {}

    public function show(Request $request): View
    {
        $user = $request->user()->load('affiliateKader');
        abort_unless($user, 403);

        $customer = null;
        $history = collect();
        $points = 0;

        if ($user->numartCustomerId()) {
            $customer = $this->numartCustomers->findById((int) $user->numartCustomerId());
            if ($customer) {
                $history = $this->numartCustomers->purchaseHistory((int) $customer->customer_id);
                $points = $this->numartCustomers->customerPoints((int) $customer->customer_id);
            }
        }

        $orders = Order::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(function (Order $order) {
                if ($order->numart_invoice && $order->tracking_status !== OrderTrackingService::DELIVERED) {
                    return $this->tracking->syncFromNumartInvoice($order);
                }

                return $order;
            });

        return view('profile.show', [
            'user' => $user,
            'customer' => $customer,
            'history' => $history,
            'orders' => $orders,
            'points' => $points,
            'verificationStatus' => $this->memberContext->verificationStatusForUser($user),
            'cartCount' => $this->cart->count(),
        ]);
    }

    public function applyReferralCode(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'referral_code' => 'required|string|max:20',
        ]);

        try {
            $this->affiliates->applyReferralCode($request->user(), $validated['referral_code']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Kode referral berhasil. Belanja Anda akan memberi komisi ke mitra kader.');
    }
}
