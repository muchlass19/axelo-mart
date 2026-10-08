<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    /** GET /api/v1/orders/recent?limit=10&status=paid&days=7 */
    public function recent(Request $request, ReportService $reports): JsonResponse
    {
        $data = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'status' => ['nullable', Rule::in(array_keys(Order::STATUSES))],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        return response()->json($reports->recentOrders($data['limit'] ?? 10, $data['status'] ?? null, $data['days'] ?? 7));
    }
}
