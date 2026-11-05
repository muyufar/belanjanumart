<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AffiliateRegistrationService;
use App\Services\NumartCustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AffiliateKaderController extends Controller
{
    public function __construct(
        protected AffiliateRegistrationService $affiliates,
        protected NumartCustomerService $customers,
    ) {}

    public function index(): View
    {
        $kaders = User::query()
            ->where('marketplace_role', User::ROLE_AFFILIATE_KADER)
            ->orderByDesc('id')
            ->paginate(20);

        return view('backoffice.affiliate.kaders', compact('kaders'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'member_card' => 'required_without:name|nullable|string|max:32',
            'name' => 'required_without:member_card|nullable|string|max:200',
            'phone' => 'required_without:member_card|nullable|string|max:30',
            'address' => 'nullable|string|max:1000',
        ]);

        if (! empty($validated['member_card'])) {
            $kartu = strtoupper(trim($validated['member_card']));
            $customer = $this->customers->findByKartu($kartu);
            if (! $customer) {
                return back()->with('error', 'Nomor kartu tidak ditemukan di POS.');
            }
            $user = $this->customers->userForCustomer((int) $customer->customer_id)
                ?? $this->customers->loginOrCreateUserFromCustomer($customer);
        } else {
            $customer = $this->customers->createCustomerInNumart(
                $validated['name'],
                $validated['phone'],
                $validated['address'] ?? null,
            );
            $user = $this->customers->loginOrCreateUserFromCustomer($customer);
        }

        if ($user->isAffiliateKader()) {
            return back()->with('error', 'Member sudah mitra kader.');
        }

        $this->affiliates->promoteToKader($user);
        $user = $user->fresh();

        return back()->with('success', 'Mitra kader dibuat. Kode referral: '.$user->affiliate_code.' · Kartu: '.$user->member_card);
    }

    public function toggle(User $user): RedirectResponse
    {
        if (! $user->isAffiliateKader()) {
            abort(404);
        }

        if ($user->affiliate_active) {
            $this->affiliates->deactivateKader($user);
            $msg = 'Mitra kader dinonaktifkan.';
        } else {
            $this->affiliates->activateKader($user);
            $msg = 'Mitra kader diaktifkan.';
        }

        return back()->with('success', $msg);
    }
}
