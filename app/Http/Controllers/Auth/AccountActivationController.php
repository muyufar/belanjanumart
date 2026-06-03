<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\NumartCustomerService;
use App\Services\WaTemporaryPasswordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountActivationController extends Controller
{
    public function __construct(
        protected NumartCustomerService $numartCustomers,
        protected WaTemporaryPasswordService $waPassword,
    ) {}

    public function create(Request $request): View
    {
        return view('auth.activate', [
            'step' => 'phone',
            'customer' => null,
            'candidates' => collect(),
            'prefillPhone' => old('phone', $request->old('phone')),
        ]);
    }

    public function lookup(Request $request): View|RedirectResponse
    {
        $request->validate([
            'phone' => 'required|string|max:30',
        ]);

        $candidates = $this->numartCustomers->findCandidatesByPhone($request->phone);

        if ($candidates->isEmpty()) {
            return back()
                ->withInput()
                ->withErrors(['phone' => 'Nomor HP tidak ditemukan di data customer Numart (semua cabang). Daftar baru jika belum pernah belanja di toko.']);
        }

        if ($candidates->count() > 1) {
            $request->session()->put('activate_phone', $request->phone);

            return view('auth.activate', [
                'step' => 'choose',
                'customer' => null,
                'candidates' => $candidates->map(fn ($c) => (object) [
                    'customer' => $c,
                    'cabang_label' => $this->numartCustomers->cabangLabel((int) ($c->customer_cabang ?? 0)),
                    'has_account' => (bool) $this->numartCustomers->userForCustomer((int) $c->customer_id),
                ]),
                'prefillPhone' => $request->phone,
            ]);
        }

        return $this->sendActivationForCustomer($request, $candidates->first());
    }

    public function choose(Request $request): View|RedirectResponse
    {
        $request->validate([
            'customer_id' => 'required|integer|min:1',
        ]);

        $customer = $this->numartCustomers->findById((int) $request->customer_id, false);

        if (! $customer || ! $this->numartCustomers->isActiveCustomer($customer)) {
            return redirect()->route('activate')->with('error', 'Data customer tidak valid atau nonaktif.');
        }

        $phone = $request->session()->get('activate_phone', $request->phone);
        $candidates = $this->numartCustomers->findCandidatesByPhone((string) $phone);
        $allowedIds = $candidates->pluck('customer_id')->map(fn ($id) => (int) $id)->all();

        if (! in_array((int) $customer->customer_id, $allowedIds, true)) {
            return redirect()->route('activate')->with('error', 'Customer tidak cocok dengan nomor HP yang dimasukkan.');
        }

        return $this->sendActivationForCustomer($request, $customer);
    }

    protected function sendActivationForCustomer(Request $request, object $customer): View|RedirectResponse
    {
        $result = $this->waPassword->issueForActivation($customer);

        if (! $result['ok']) {
            if (str_contains($result['message'] ?? '', 'sudah aktif')) {
                return redirect()
                    ->route('login')
                    ->with('error', $result['message']);
            }

            return back()
                ->withInput()
                ->withErrors(['phone' => $result['message'] ?? 'Gagal aktivasi.']);
        }

        $request->session()->forget('activate_phone');
        $phone = $this->numartCustomers->normalizePhone((string) $customer->customer_tlpn);

        return view('auth.activate', [
            'step' => 'sent',
            'customer' => $customer,
            'candidates' => collect(),
            'cabangLabel' => $this->numartCustomers->cabangLabel((int) ($customer->customer_cabang ?? 0)),
            'maskedPhone' => $this->waPassword->maskPhone($phone),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        return redirect()->route('activate')->with('error', 'Gunakan alur aktivasi: masukkan HP lalu terima password via WhatsApp.');
    }
}
