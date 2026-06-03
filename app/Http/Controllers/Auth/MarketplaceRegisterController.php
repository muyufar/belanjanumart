<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\NumartCustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketplaceRegisterController extends Controller
{
    public function __construct(
        protected NumartCustomerService $numartCustomers,
    ) {}

    public function create(): View
    {
        return view('auth.register', ['cartCount' => 0]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'required|string|max:30',
            'address' => 'nullable|string|max:1000',
        ]);

        $phone = $this->numartCustomers->normalizePhone($validated['phone']);

        if ($this->numartCustomers->userForPhone($validated['phone'])) {
            return back()->withInput()->withErrors(['phone' => 'Nomor HP sudah terdaftar. Silakan masuk.']);
        }

        $customer = $this->numartCustomers->findByPhone($validated['phone']);

        if ($customer) {
            if ($this->numartCustomers->userForCustomer((int) $customer->customer_id)) {
                return redirect()->route('login')->with('error', 'Nomor ini sudah punya akun marketplace. Silakan masuk.');
            }

            return redirect()
                ->route('activate')
                ->with('success', 'Nomor sudah ada di data customer Numart. Lanjut aktivasi untuk menerima password via WhatsApp.')
                ->withInput(['phone' => $validated['phone']]);
        }

        try {
            $this->numartCustomers->createCustomerInNumart(
                $validated['name'],
                $validated['phone'],
                $validated['address'] ?? '',
                $validated['email'] ?? null
            );
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()->withErrors(['phone' => 'Gagal menyimpan data ke Numart. Coba lagi atau hubungi toko.']);
        }

        return redirect()
            ->route('activate')
            ->with('success', 'Registrasi tersimpan di Numart. Aktivasi akun untuk menerima password via WhatsApp.')
            ->withInput(['phone' => $validated['phone']]);
    }
}
