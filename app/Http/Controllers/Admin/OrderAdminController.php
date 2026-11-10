<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderAdminController extends Controller
{
    public function index(Request $request): View
    {
        $orders = Order::with('payment')
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin.orders.index', [
            'orders' => $orders,
            'cartCount' => 0,
        ]);
    }

    /**
     * Konfirmasi lunas dari admin belanjanumart. Dulu dilakukan dari menu POS lama (POST /api/numart/orders/{id}/confirm-payment);
     * setelah cutover POS lama tidak dipakai lagi, jadi tombolnya ada di sini. Nota dibuat di sistem toko aktif (NUMART_SOURCE).
     */
    public function confirmPayment(Order $order, OrderPaymentService $payments): RedirectResponse
    {
        [$status, $payload] = $payments->confirm($order);

        return back()->with($status < 400 ? 'success' : 'error', $order->order_number.': '.$payload['message']);
    }

    public function cancel(Order $order, OrderPaymentService $payments): RedirectResponse
    {
        try {
            $payments->cancel($order, 'Dibatalkan admin');

            return back()->with('success', $order->order_number.': pesanan dibatalkan, reservasi stok dilepas.');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', $order->order_number.': '.$e->getMessage());
        }
    }
}
