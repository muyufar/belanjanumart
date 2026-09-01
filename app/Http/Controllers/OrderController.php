<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\BranchWhatsAppService;
use App\Services\CheckoutService;
use App\Services\MemberContextService;
use App\Services\OrderTrackingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        protected BranchWhatsAppService $whatsapp,
        protected MemberContextService $memberContext,
        protected CheckoutService $checkout,
        protected OrderTrackingService $tracking,
    ) {}

    public function show(Request $request, Order $order): View
    {
        abort_unless($order->user_id === $request->user()?->id, 403);

        $order->load(['items', 'user']);

        if ($order->numart_invoice) {
            $order = $this->tracking->syncFromNumartInvoice($order);
        }

        return view('orders.show', [
            'order' => $order,
            'trackingSteps' => $this->tracking->timelineSteps($order),
            'trackingLabel' => OrderTrackingService::label($order->tracking_status),
            'cartCount' => 0,
            'waOrderUrl' => $this->whatsapp->webUrlForOrder($order, 'order'),
            'waProofUrl' => $this->whatsapp->webUrlForOrder($order, 'transfer_proof'),
            'qrisUrl' => $this->memberContext->branchQrisUrl((int) $order->fulfillment_cabang),
        ]);
    }

    public function uploadProof(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()?->id, 403);

        $request->validate([
            'payment_proof' => 'required|image|max:5120',
        ]);

        try {
            $path = $request->file('payment_proof')->store('payments/proofs', 'public');
            $this->checkout->submitPaymentProof($order, $path);

            return redirect()
                ->route('orders.show', $order)
                ->with('success', 'Bukti transfer terupload. Kirim konfirmasi via WhatsApp ke cabang.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
