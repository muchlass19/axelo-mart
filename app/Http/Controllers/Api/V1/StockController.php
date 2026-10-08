<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockController extends Controller
{
    /** GET /api/v1/stock?id=1  atau  /api/v1/stock?name=kopi */
    public function check(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required_without:name', 'nullable', 'integer'],
            'name' => ['required_without:id', 'nullable', 'string', 'max:100'],
        ]);

        if (! empty($data['id'])) {
            $product = Product::find($data['id']);

            return $product
                ? response()->json(['data' => [$this->stockRow($product)]])
                : response()->json(['message' => 'Produk tidak ditemukan.', 'data' => []], 404);
        }

        $products = Product::where('name', 'like', "%{$data['name']}%")->orderBy('name')->limit(20)->get();

        return response()->json([
            'query' => $data['name'],
            'count' => $products->count(),
            'data' => $products->map(fn ($p) => $this->stockRow($p)),
        ]);
    }

    /** GET /api/v1/stock/low?threshold=5 */
    public function low(Request $request, ReportService $reports): JsonResponse
    {
        $threshold = (int) ($request->validate(['threshold' => ['nullable', 'integer', 'min:0']])['threshold'] ?? ReportService::threshold());
        $products = $reports->lowStock($threshold);

        return response()->json([
            'threshold' => $threshold,
            'count' => $products->count(),
            'data' => $products->map(fn ($p) => $this->stockRow($p)),
        ]);
    }

    private function stockRow(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'stock' => $product->stock,
            'status' => $product->status,
            'is_available' => $product->isActive() && $product->stock > 0,
            'price' => $product->price,
        ];
    }
}
