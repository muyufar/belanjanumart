<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Bri\BriPaymentService;
use App\Services\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function show(Order $order): View
    {
        $order->load(['items', 'payment']);

        return view('orders.show', [
            'order' => $order,
            'cartCount' => 0,
        ]);
    }

    public function checkPayment(Order $order, BriPaymentService $bri, CheckoutService $checkout): RedirectResponse
    {
        $order->load('payment');
        $payment = $order->payment;

        if (! $payment || $order->isPaid()) {
            return redirect()->route('orders.show', $order);
        }

        if (config('bri.mock')) {
            $checkout->markPaid($order);

            return redirect()->route('orders.show', $order)->with('success', 'Pembayaran simulasi berhasil (mode mock).');
        }

        $status = $bri->checkStatus($payment);
        $paid = ($status['data']['statusBayar'] ?? $status['statusBayar'] ?? '') === 'Y';

        if ($paid) {
            $checkout->markPaid($order);

            return redirect()->route('orders.show', $order)->with('success', 'Pembayaran terkonfirmasi.');
        }

        return redirect()->route('orders.show', $order)->with('error', 'Pembayaran belum masuk. Coba lagi beberapa saat.');
    }
}
