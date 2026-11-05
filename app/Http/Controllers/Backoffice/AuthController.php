<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Support\MarketplaceAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check() && MarketplaceAccess::isBackoffice(Auth::user())) {
            return redirect()->route('backoffice.dashboard');
        }

        return view('backoffice.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withInput()->withErrors(['email' => 'Email atau password salah.']);
        }

        $request->session()->regenerate();

        if (! MarketplaceAccess::isBackoffice($request->user())) {
            Auth::logout();

            return back()->withInput()->withErrors([
                'email' => 'Akun ini bukan staff backoffice. Hubungi admin untuk akses.',
            ]);
        }

        return redirect()->intended(route('backoffice.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('backoffice.login');
    }
}
