<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\AffiliateCommissionService;
use App\Services\OrderPaymentService;
use App\Services\OrderTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NumartOrderApiController extends Controller
{
    public function __construct(
        protected OrderTrackingService $tracking,
    ) {}

    public function confirmPayment(Request $request, int $order): JsonResponse
    {
        $model = Order::with(['items', 'user'])->find($order);

        if (! $model) {
            return response()->json(['success' => false, 'message' => 'Pesanan tidak ditemukan.'], 404);
        }

        [$status, $payload] = app(OrderPaymentService::class)->confirm($model);

        return response()->json($payload, $status);
    }

    public function accrueAffiliate(int $order): JsonResponse
    {
        $model = Order::with(['items', 'user'])->find($order);

        if (! $model) {
            return response()->json(['success' => false, 'message' => 'Pesanan tidak ditemukan.'], 404);
        }

        $commission = app(AffiliateCommissionService::class)->accrueForOrder($model);

        return response()->json([
            'success' => true,
            'created' => $commission !== null,
            'commission_id' => $commission?->id,
            'message' => $commission
                ? 'Komisi affiliate tercatat.'
                : 'Tidak ada komisi (member tanpa kader, kader nonaktif, atau pesanan belum lunas).',
        ]);
    }

    public function updateTracking(Request $request, int $order): JsonResponse
    {
        $model = Order::find($order);

        if (! $model) {
            return response()->json(['success' => false, 'message' => 'Pesanan tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'tracking_status' => ['required', 'string', Rule::in(OrderTrackingService::steps())],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->tracking->updateTracking(
                $model,
                $validated['tracking_status'],
                $validated['note'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Status pengiriman diperbarui.',
                'tracking_status' => $validated['tracking_status'],
                'tracking_label' => OrderTrackingService::label($validated['tracking_status']),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
