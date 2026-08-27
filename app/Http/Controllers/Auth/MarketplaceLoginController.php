<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\MemberContextService;
use App\Services\NumartCustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MarketplaceLoginController extends Controller
{
    public function __construct(
        protected NumartCustomerService $numartCustomers,
        protected MemberContextService $memberContext,
    ) {}

    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('shop.index');
        }

        return view('auth.login', ['cartCount' => 0]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'card_number' => 'required|string|max:32',
        ]);

        $customer = $this->numartCustomers->findByKartu($validated['card_number']);

        if (! $customer) {
            return back()
                ->withInput()
                ->withErrors(['card_number' => 'Nomor kartu tidak ditemukan atau tidak aktif.']);
        }

        $user = $this->numartCustomers->loginOrCreateUserFromCustomer($customer);

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        if ($this->memberContext->needsVerificationUpload($user)) {
            return redirect()
                ->route('member.verification.create')
                ->with('info', 'Lengkapi verifikasi akun untuk dapat menggunakan COD.');
        }

        return redirect()->intended(route('shop.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
