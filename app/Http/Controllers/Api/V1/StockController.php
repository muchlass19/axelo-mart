<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CatalogService;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockController extends Controller
{
    /** GET /api/v1/stock?id=1  atau  /api/v1/stock?name=kopi */
    public function check(Request $request, CatalogService $catalog): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required_without:name', 'nullable', 'integer'],
            'name' => ['required_without:id', 'nullable', 'string', 'max:100'],
        ], [
            'required_without' => 'Kirim parameter id atau name.',
        ]);

        $id = empty($data['id']) ? null : (int) $data['id'];
        $rows = $catalog->stockCheck($id, $data['name'] ?? null)->map(fn ($p) => CatalogService::stockRow($p));

        if ($id) {
            return $rows->isNotEmpty()
                ? response()->json(['data' => $rows])
                : response()->json(['message' => 'Produk tidak ditemukan.', 'data' => []], 404);
        }

        return response()->json(['query' => $data['name'], 'count' => $rows->count(), 'data' => $rows]);
    }

    /** GET /api/v1/stock/low?threshold=5 */
    public function low(Request $request, ReportService $reports): JsonResponse
    {
        $threshold = (int) ($request->validate(['threshold' => ['nullable', 'integer', 'min:0']])['threshold'] ?? ReportService::threshold());
        $products = $reports->lowStock($threshold);

        return response()->json([
            'threshold' => $threshold,
            'count' => $products->count(),
            'data' => $products->map(fn ($p) => CatalogService::stockRow($p)),
        ]);
    }
}
