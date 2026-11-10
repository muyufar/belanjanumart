<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\NumartErpClient;
use App\Services\OrderPaymentService;
use App\Services\OrderTrackingService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * POST /api/webhooks/numart-erp — event dari NUMART ERP (docs/integrasi.md di repo numart-erp).
 * Body: {"id","event","data":{…},"dibuat"}; header X-Client-Id, X-Timestamp, X-Signature (HMAC path tanpa query), X-Event-Id.
 * Idempoten: X-Event-Id dicatat di numart_erp_webhook_events dalam transaksi yang sama dengan efeknya;
 * event yang sudah diproses dijawab 200 tanpa efek. Gagal diproses → 500 → ERP mengulang.
 */
class NumartErpWebhookController extends Controller
{
    public function __invoke(Request $request, OrderTrackingService $tracking, OrderPaymentService $payments): JsonResponse
    {
        $secret = (string) config('services.numart_erp.secret');
        $body = $request->getContent();
        $path = '/'.ltrim((string) parse_url($request->getRequestUri(), PHP_URL_PATH), '/');

        if ($secret === ''
            || ! hash_equals((string) config('services.numart_erp.client_id'), (string) $request->header('X-Client-Id'))
            || ! NumartErpClient::verifyWebhook($secret, (string) $request->header('X-Timestamp'), $path, $body, (string) $request->header('X-Signature'))) {
            Log::warning('Webhook ERP ditolak: tanda tangan tidak valid', ['path' => $path, 'event_id' => $request->header('X-Event-Id')]);

            return response()->json(['message' => 'Tanda tangan tidak valid.'], 401);
        }

        $eventId = trim((string) $request->header('X-Event-Id'));
        $json = json_decode($body, true);
        if ($eventId === '' || ! is_array($json) || empty($json['event'])) {
            return response()->json(['message' => 'X-Event-Id dan event wajib ada.'], 422);
        }
        $event = (string) $json['event'];
        $data = (array) ($json['data'] ?? []);
        $orderNo = isset($data['nomor_pesanan']) ? (string) $data['nomor_pesanan'] : null;

        try {
            $result = DB::transaction(function () use ($eventId, $event, $data, $orderNo, $json, $tracking, $payments) {
                DB::table('numart_erp_webhook_events')->insert([
                    'event_id' => $eventId, 'event' => $event, 'order_number' => $orderNo,
                    'payload' => json_encode($json), 'created_at' => now(), 'updated_at' => now(),
                ]);
                $result = $this->handle($event, $data, $orderNo, $tracking, $payments);
                DB::table('numart_erp_webhook_events')->where('event_id', $eventId)->update(['result' => mb_substr($result, 0, 255)]);

                return $result;
            });
        } catch (UniqueConstraintViolationException) {
            return response()->json(['message' => 'Sudah diproses.', 'duplikat' => true]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Gagal memproses: '.$e->getMessage()], 500);
        }

        return response()->json(['message' => $result]);
    }

    private function handle(string $event, array $data, ?string $orderNo, OrderTrackingService $tracking, OrderPaymentService $payments): string
    {
        $order = $orderNo ? Order::where('order_number', $orderNo)->lockForUpdate()->first() : null;
        if (! $order) {
            return 'pesanan tidak dikenal — diabaikan';   // 200 agar ERP tidak mengulang tanpa akhir
        }

        switch ($event) {
            case 'pesanan.dipenuhi':
                // Nota dibuat ERP (dari belanjanumart atau dipenuhi ulang). Samakan status lokal bila belum.
                $update = ['numart_invoice' => (string) ($data['nota'] ?? $order->numart_invoice), 'numart_source' => 'erp'];
                if (! in_array($order->status, ['processing', 'paid', 'shipped', 'completed'], true)) {
                    $update += ['status' => 'processing', 'tracking_status' => OrderTrackingService::PREPARING, 'tracking_updated_at' => now()];
                }
                if (! $order->paid_at) {
                    $update['paid_at'] = now();
                }
                $order->update($update);

                return 'nota '.$update['numart_invoice'];

            case 'pesanan.dibatalkan':
                $payments->cancelledByStore($order, $data['alasan'] ?? null);

                return 'pesanan dibatalkan toko';

            case 'pengiriman.diperbarui':
                $status = (string) ($data['status_belanja'] ?? '');
                if (! array_key_exists($status, OrderTrackingService::labels())) {
                    return 'status_belanja tidak dikenal: '.$status;
                }
                $tracking->updateTracking($order, $status, $data['catatan'] ?? null);

                return 'tracking '.$status;

            default:
                return 'event tidak ditangani: '.$event;
        }
    }
}
