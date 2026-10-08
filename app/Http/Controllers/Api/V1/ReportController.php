<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    /**
     * GET /api/v1/reports/sales?from=2026-09-01&to=2026-09-30&limit=5
     * Default: 30 hari terakhir. Hanya order paid/shipped/completed (berdasarkan paid_at).
     */
    public function sales(Request $request, ReportService $reports): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $to = isset($data['to']) ? Carbon::parse($data['to']) : today();
        $from = isset($data['from']) ? Carbon::parse($data['from']) : $to->copy()->subDays(29);
        $summary = $reports->salesSummary($from, $to);

        return response()->json([
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'counted_statuses' => Order::SALES_STATUSES,
            ...$summary,
            'total_revenue_formatted' => rupiah($summary['total_revenue']),
            'top_products' => $reports->topProducts($from, $to, $data['limit'] ?? 5),
        ]);
    }
}
