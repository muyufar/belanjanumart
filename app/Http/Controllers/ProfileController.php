<?php

namespace App\Http\Controllers;

use App\Services\CartSessionService;
use App\Services\NumartCustomerService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        protected NumartCustomerService $numartCustomers,
        protected CartSessionService $cart,
    ) {}

    public function show(Request $request): View
    {
        $user = $request->user();
        abort_unless($user, 403);

        $customer = null;
        $history = collect();
        $points = 0;

        if ($user->numart_customer_id) {
            $customer = $this->numartCustomers->findById((int) $user->numart_customer_id);
            if ($customer) {
                $history = $this->numartCustomers->purchaseHistory((int) $customer->customer_id);
                $points = $this->numartCustomers->customerPoints((int) $customer->customer_id);
            }
        }

        return view('profile.show', [
            'user' => $user,
            'customer' => $customer,
            'history' => $history,
            'points' => $points,
            'cartCount' => $this->cart->count(),
        ]);
    }
}
