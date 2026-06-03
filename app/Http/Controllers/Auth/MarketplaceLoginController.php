<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\NumartCustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class MarketplaceLoginController extends Controller
{
    public function __construct(
        protected NumartCustomerService $numartCustomers,
    ) {}

    public function create(): View
    {
        return view('auth.login', ['cartCount' => 0]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:30',
            'password' => 'required',
        ]);

        $user = $this->resolveUserForLogin($validated['phone']);

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            $candidates = $this->numartCustomers->findCandidatesByPhone($validated['phone']);
            $customer = $candidates->count() === 1 ? $candidates->first() : null;
            if ($customer && ! $this->numartCustomers->userForCustomer((int) $customer->customer_id)) {
                return back()
                    ->withInput()
                    ->withErrors(['phone' => 'Customer ditemukan di Numart tetapi belum diaktifkan.'])
                    ->with('activate_hint', true);
            }

            return back()->withErrors(['phone' => 'Nomor HP atau password salah.'])->onlyInput('phone');
        }

        if ($user->numart_customer_id) {
            $customer = $this->numartCustomers->findById((int) $user->numart_customer_id);
            if ($customer) {
                $this->numartCustomers->applyCustomerToUser($user, $customer);
            }
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        if ($user->must_change_password) {
            return redirect()
                ->route('password.change')
                ->with('success', 'Masuk berhasil. Ganti password sementara yang dikirim via WhatsApp.');
        }

        return redirect()->intended(route('shop.index'));
    }

    protected function resolveUserForLogin(string $phone): ?\App\Models\User
    {
        $user = $this->numartCustomers->userForPhone($phone);
        if ($user) {
            return $user;
        }

        $normalized = $this->numartCustomers->normalizePhone($phone);
        foreach ($this->numartCustomers->findCandidatesByPhone($phone) as $customer) {
            $linked = $this->numartCustomers->userForCustomer((int) $customer->customer_id);
            if ($linked && $this->numartCustomers->normalizePhone((string) $linked->phone) === $normalized) {
                return $linked;
            }
        }

        return null;
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('shop.index');
    }
}
