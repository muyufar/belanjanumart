<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MemberVerificationAdminController extends Controller
{
    public function index(): View
    {
        $pending = User::query()
            ->where('member_verification_status', 'pending')
            ->orderByDesc('verification_submitted_at')
            ->get();

        return view('admin.members.verify', [
            'members' => $pending,
            'cartCount' => 0,
        ]);
    }

    public function approve(User $user): RedirectResponse
    {
        $user->update([
            'member_verification_status' => 'approved',
            'verification_reviewed_at' => now(),
        ]);

        return back()->with('success', 'Member '.$user->member_card.' disetujui.');
    }

    public function reject(User $user): RedirectResponse
    {
        $user->update([
            'member_verification_status' => 'rejected',
            'verification_reviewed_at' => now(),
        ]);

        return back()->with('success', 'Verifikasi ditolak. Member dapat upload ulang.');
    }
}
