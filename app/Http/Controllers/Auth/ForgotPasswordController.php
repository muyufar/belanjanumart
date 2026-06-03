<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\NumartCustomerService;
use App\Services\WaTemporaryPasswordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function __construct(
        protected NumartCustomerService $numartCustomers,
        protected WaTemporaryPasswordService $waPassword,
    ) {}

    public function create(Request $request): View
    {
        return view('auth.forgot-password', [
            'step' => 'phone',
            'candidates' => collect(),
            'prefillPhone' => old('phone', $request->old('phone')),
        ]);
    }

    public function send(Request $request): View|RedirectResponse
    {
        $request->validate([
            'phone' => 'required|string|max:30',
        ]);

        $phone = $request->phone;
        $user = $this->numartCustomers->userForPhone($phone);

        if ($user && $user->numart_customer_id) {
            $customer = $this->numartCustomers->findById((int) $user->numart_customer_id, false);
            if ($customer) {
                return $this->completeReset($request, $customer);
            }
        }

        $candidates = $this->numartCustomers->findCandidatesByPhone($phone);
        $withAccount = $candidates->filter(
            fn ($c) => (bool) $this->numartCustomers->userForCustomer((int) $c->customer_id)
        )->values();

        if ($withAccount->isEmpty()) {
            if ($candidates->isNotEmpty()) {
                return back()
                    ->withInput()
                    ->withErrors(['phone' => 'Akun belanja online belum diaktivasi. Gunakan menu Aktivasi Akun.'])
                    ->with('activate_hint', true);
            }

            return back()
                ->withInput()
                ->withErrors(['phone' => 'Nomor HP tidak ditemukan di data customer Numart.']);
        }

        if ($withAccount->count() > 1) {
            $request->session()->put('forgot_phone', $phone);

            return view('auth.forgot-password', [
                'step' => 'choose',
                'candidates' => $withAccount->map(fn ($c) => (object) [
                    'customer' => $c,
                    'cabang_label' => $this->numartCustomers->cabangLabel((int) ($c->customer_cabang ?? 0)),
                ]),
                'prefillPhone' => $phone,
            ]);
        }

        return $this->completeReset($request, $withAccount->first());
    }

    public function choose(Request $request): View|RedirectResponse
    {
        $request->validate([
            'customer_id' => 'required|integer|min:1',
        ]);

        $customer = $this->numartCustomers->findById((int) $request->customer_id, false);

        if (! $customer || ! $this->numartCustomers->isActiveCustomer($customer)) {
            return redirect()->route('password.forgot')->with('error', 'Data customer tidak valid.');
        }

        if (! $this->numartCustomers->userForCustomer((int) $customer->customer_id)) {
            return redirect()->route('password.forgot')->with('error', 'Akun online untuk customer ini belum ada.');
        }

        $phone = (string) $request->session()->get('forgot_phone', '');
        $candidates = $this->numartCustomers->findCandidatesByPhone($phone);
        $allowedIds = $candidates
            ->filter(fn ($c) => $this->numartCustomers->userForCustomer((int) $c->customer_id))
            ->pluck('customer_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (! in_array((int) $customer->customer_id, $allowedIds, true)) {
            return redirect()->route('password.forgot')->with('error', 'Customer tidak cocok dengan nomor HP.');
        }

        return $this->completeReset($request, $customer);
    }

    protected function completeReset(Request $request, object $customer): View|RedirectResponse
    {
        $result = $this->waPassword->issueForPasswordReset($customer);

        if (! $result['ok']) {
            return back()
                ->withInput()
                ->withErrors(['phone' => $result['message'] ?? 'Gagal reset password.']);
        }

        $request->session()->forget('forgot_phone');
        $phone = $this->numartCustomers->normalizePhone((string) $customer->customer_tlpn);

        return view('auth.forgot-password', [
            'step' => 'sent',
            'customer' => $customer,
            'cabangLabel' => $this->numartCustomers->cabangLabel((int) ($customer->customer_cabang ?? 0)),
            'maskedPhone' => $this->waPassword->maskPhone($phone),
        ]);
    }
}
