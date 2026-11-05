<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AffiliateRegistrationService;
use App\Services\NumartCustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AffiliateKaderAdminController extends Controller
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

        return view('admin.affiliate.kaders', [
            'kaders' => $kaders,
            'cartCount' => 0,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'member_card' => 'required|string|max:32',
        ]);

        $kartu = strtoupper(trim($validated['member_card']));
        $customer = $this->customers->findByKartu($kartu);

        if (! $customer) {
            return back()->with('error', 'Nomor kartu member tidak ditemukan di POS.');
        }

        $user = $this->customers->userForCustomer((int) $customer->customer_id)
            ?? $this->customers->loginOrCreateUserFromCustomer($customer);

        if ($user->isAffiliateKader()) {
            return back()->with('error', 'Member sudah terdaftar sebagai mitra kader.');
        }

        $this->affiliates->promoteToKader($user);

        return back()->with('success', 'Mitra kader aktif: '.$user->name.' · Kode '.$user->fresh()->affiliate_code);
    }

    public function toggleActive(User $user): RedirectResponse
    {
        if (! $user->isAffiliateKader()) {
            abort(404);
        }

        if ($user->affiliate_active) {
            $this->affiliates->deactivateKader($user);
            $msg = 'Mitra kader dinonaktifkan.';
        } else {
            $this->affiliates->activateKader($user);
            $msg = 'Mitra kader diaktifkan kembali.';
        }

        return back()->with('success', $msg);
    }
}
