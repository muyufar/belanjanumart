<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BriWebhookController extends Controller
{
    public function payment(Request $request, CheckoutService $checkout): JsonResponse
    {
        $secret = config('bri.webhook_secret');
        if ($secret && $request->header('X-Webhook-Secret') !== $secret) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        Log::info('BRI webhook', $request->all());

        $custCode = $request->input('custCode')
            ?? $request->input('customer_code')
            ?? $request->input('data.custCode');

        $statusBayar = $request->input('statusBayar')
            ?? $request->input('data.statusBayar')
            ?? $request->input('paymentFlagStatus');

        if (! $custCode) {
            return response()->json(['message' => 'custCode required'], 422);
        }

        $payment = Payment::where('cust_code', $custCode)->first();
        if (! $payment) {
            return response()->json(['message' => 'payment not found'], 404);
        }

        $paid = in_array($statusBayar, ['Y', '00', 'paid', 1, '1'], true);

        if ($paid) {
            /** @var Order $order */
            $order = $payment->order;
            $checkout->markPaid($order);
        }

        return response()->json(['message' => 'ok']);
    }
}
