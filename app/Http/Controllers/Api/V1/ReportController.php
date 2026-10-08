<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        return response()->json($reports->salesReport($data['from'] ?? null, $data['to'] ?? null, $data['limit'] ?? 5));
    }
}
