<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
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
}
