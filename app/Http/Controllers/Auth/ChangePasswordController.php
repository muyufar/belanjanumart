<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ChangePasswordController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        return view('auth.change-password', ['cartCount' => 0]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed|different:current_password',
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Password saat ini tidak sesuai.']);
        }

        $user->password = Hash::make($validated['password']);
        $user->must_change_password = false;
        $user->save();

        return redirect()
            ->route('shop.index')
            ->with('success', 'Password berhasil diperbarui.');
    }
}
