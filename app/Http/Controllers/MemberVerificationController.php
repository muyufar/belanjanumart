<?php

namespace App\Http\Controllers;

use App\Services\MemberContextService;
use App\Services\NumartCustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MemberVerificationController extends Controller
{
    public function __construct(
        protected MemberContextService $memberContext,
        protected NumartCustomerService $numartCustomers,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $this->memberContext->needsVerificationUpload($user)) {
            return redirect()->route('shop.index');
        }

        return view('member.verification', [
            'user' => $user,
            'verificationStatus' => $this->memberContext->verificationStatusForUser($user),
            'isGrosir' => $this->memberContext->isGrosirMember($user),
            'cartCount' => 0,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $isGrosir = $this->memberContext->isGrosirMember($user);

        $rules = [
            'ktp' => 'required|image|max:5120',
        ];

        if ($isGrosir) {
            $rules['business_photo'] = 'required|image|max:5120';
        }

        $validated = $request->validate($rules);

        $ktpPath = $request->file('ktp')->store('verifications/ktp', 'public');
        $businessPath = null;

        if ($isGrosir && $request->hasFile('business_photo')) {
            $businessPath = $request->file('business_photo')->store('verifications/business', 'public');
        }

        $user->update([
            'ktp_path' => $ktpPath,
            'business_photo_path' => $businessPath,
            'member_verification_status' => 'pending',
            'verification_submitted_at' => now(),
            'verification_reviewed_at' => null,
        ]);

        if ($user->numart_customer_id) {
            $this->numartCustomers->syncVerificationToCustomer(
                (int) $user->numart_customer_id,
                $ktpPath,
                $businessPath,
                'pending',
            );
        }

        return redirect()
            ->route('shop.index')
            ->with('success', 'Dokumen verifikasi terkirim. Menunggu persetujuan kasir di POS untuk COD.');
    }
}
