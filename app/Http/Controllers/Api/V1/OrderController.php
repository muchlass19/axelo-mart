<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    /** GET /api/v1/orders/recent?limit=10&status=paid&days=7 */
    public function recent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'status' => ['nullable', Rule::in(array_keys(Order::STATUSES))],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);
        $days = $data['days'] ?? 7;

        $statusCounts = Order::where('created_at', '>=', now()->subDays($days))
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($n) => (int) $n);

        $orders = Order::with('items')
            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->limit($data['limit'] ?? 10)
            ->get();

        return response()->json([
            'summary' => [
                'days' => $days,
                'total_orders' => $statusCounts->sum(),
                'by_status' => $statusCounts,
            ],
            'data' => $orders->map(fn (Order $order) => [
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'status' => $order->status,
                'status_label' => $order->statusLabel(),
                'total_amount' => $order->total_amount,
                'total_amount_formatted' => rupiah($order->total_amount),
                'items_count' => $order->items->sum('quantity'),
                'items' => $order->items->map(fn ($i) => ['product_name' => $i->product_name, 'quantity' => $i->quantity, 'subtotal' => $i->subtotal]),
                'payment_type' => $order->payment_type,
                'created_at' => $order->created_at->toIso8601String(),
                'paid_at' => $order->paid_at?->toIso8601String(),
            ]),
        ]);
    }
}
