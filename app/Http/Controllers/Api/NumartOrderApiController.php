<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\NumartOrderSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NumartOrderApiController extends Controller
{
    public function confirmPayment(Request $request, int $order): JsonResponse
    {
        $model = Order::with(['items', 'user'])->find($order);

        if (! $model) {
            return response()->json(['success' => false, 'message' => 'Pesanan tidak ditemukan.'], 404);
        }

        if ($model->numart_invoice) {
            return response()->json([
                'success' => true,
                'message' => 'Pesanan sudah masuk invoice POS.',
                'invoice' => $model->numart_invoice,
            ]);
        }

        $allowed = ['proof_submitted', 'pending_cod'];

        if ($model->payment_method === 'transfer' && ! in_array($model->status, ['proof_submitted'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Transfer belum ada bukti upload atau sudah diproses.',
            ], 422);
        }

        if ($model->payment_method === 'cod' && $model->status !== 'pending_cod') {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan COD tidak dalam status menunggu proses.',
            ], 422);
        }

        if (! in_array($model->status, $allowed, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Status pesanan tidak dapat dikonfirmasi.',
            ], 422);
        }

        if ($model->payment_method === 'transfer' && ! $model->payment_proof_path) {
            return response()->json([
                'success' => false,
                'message' => 'Bukti transfer belum diupload member.',
            ], 422);
        }

        try {
            $invoice = app(NumartOrderSyncService::class)->syncPaidOrder($model);
            $model->update(['paid_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'Pembayaran dikonfirmasi. Invoice POS: '.$invoice,
                'invoice' => $invoice,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Gagal sinkron ke POS: '.$e->getMessage(),
            ], 500);
        }
    }
}
