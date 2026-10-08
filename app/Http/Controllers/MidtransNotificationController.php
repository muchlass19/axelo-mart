<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint HTTP Notification (webhook) Midtrans: POST /midtrans/notification
 * Dikecualikan dari CSRF (lihat bootstrap/app.php). Saat lokal, expose dengan ngrok.
 */
class MidtransNotificationController extends Controller
{
    public function __invoke(Request $request, MidtransService $midtrans): JsonResponse
    {
        if (! $midtrans->isConfigured()) {
            return response()->json(['message' => 'Midtrans belum dikonfigurasi.'], 503);
        }

        $payload = $request->all();

        if (! $midtrans->verifySignature($payload)) {
            return response()->json(['message' => 'Signature tidak valid.'], 403);
        }

        $order = Order::where('order_number', $payload['order_id'])->first();
        if (! $order) {
            // 200 supaya Midtrans tidak retry terus (mis. saat "Test notification" dari dashboard).
            return response()->json(['message' => 'Order tidak ditemukan, diabaikan.']);
        }

        $updated = $midtrans->applyTransaction($order, $payload);

        return response()->json([
            'message' => $updated ? 'Status order diperbarui.' : 'Tidak ada perubahan.',
            'order_status' => $order->fresh()->status,
        ]);
    }
}
