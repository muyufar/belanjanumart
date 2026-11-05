<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AffiliateCommission;
use App\Services\AffiliateCommissionService;
use App\Services\AffiliateSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AffiliateCommissionAdminController extends Controller
{
    public function __construct(
        protected AffiliateSettingsService $settings,
        protected AffiliateCommissionService $commissions,
    ) {}

    public function settings(): View
    {
        return view('admin.affiliate.settings', [
            'settings' => $this->settings->all(),
            'cartCount' => 0,
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'warung' => 'required|numeric|min:0|max:100',
            'prioritas' => 'required|numeric|min:0|max:100',
            'basis' => 'required|in:subtotal,grand_total',
        ]);

        $this->settings->update($validated);

        return back()->with('success', 'Persentase komisi affiliate diperbarui.');
    }

    public function commissions(Request $request): View
    {
        $status = (string) $request->query('status', AffiliateCommission::STATUS_ACCRUED);

        $rows = AffiliateCommission::with(['affiliate', 'referredUser', 'order'])
            ->when(in_array($status, [AffiliateCommission::STATUS_ACCRUED, AffiliateCommission::STATUS_PAID], true), fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.affiliate.commissions', [
            'commissions' => $rows,
            'status' => $status,
            'cartCount' => 0,
        ]);
    }

    public function markPaid(Request $request, AffiliateCommission $commission): RedirectResponse
    {
        if ($commission->status !== AffiliateCommission::STATUS_ACCRUED) {
            return back()->with('error', 'Komisi sudah diproses.');
        }

        $note = $request->validate(['paid_note' => 'nullable|string|max:500'])['paid_note'] ?? null;
        $this->commissions->markPaid($commission, $note);

        return back()->with('success', 'Komisi ditandai sudah dibayar.');
    }
}
