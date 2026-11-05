<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\AffiliateCommission;
use App\Services\AffiliateCommissionService;
use App\Services\AffiliateSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AffiliateCommissionController extends Controller
{
    public function __construct(
        protected AffiliateSettingsService $settings,
        protected AffiliateCommissionService $commissions,
    ) {}

    public function settings(): View
    {
        return view('backoffice.affiliate.settings', [
            'settings' => $this->settings->all(),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'margin_share_min' => 'required|numeric|min:0|max:100',
            'margin_share_max' => 'required|numeric|min:0|max:100|gte:margin_share_min',
        ]);

        $this->settings->updateMarginShare($validated['margin_share_min'], $validated['margin_share_max']);

        return back()->with('success', 'Pengaturan komisi margin bersih diperbarui.');
    }

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', AffiliateCommission::STATUS_ACCRUED);

        $commissions = AffiliateCommission::with(['affiliate', 'referredUser', 'order'])
            ->when(in_array($status, [AffiliateCommission::STATUS_ACCRUED, AffiliateCommission::STATUS_PAID], true), fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('backoffice.affiliate.commissions', compact('commissions', 'status'));
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
