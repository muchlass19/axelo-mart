<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function __construct(private CatalogService $catalog) {}

    /** GET /api/v1/products?search=&status=active|inactive&available=1&per_page=20 */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in([Product::STATUS_ACTIVE, Product::STATUS_INACTIVE])],
            'available' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $products = $this->catalog->query([...$filters, 'available' => $request->boolean('available')])
            ->with('images')
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();

        return ProductResource::collection($products);
    }

    /** GET /api/v1/products/{id} */
    public function show(int $id): ProductResource|JsonResponse
    {
        $product = $this->catalog->find($id);

        return $product
            ? new ProductResource($product)
            : response()->json(['message' => 'Produk tidak ditemukan.'], 404);
    }
}
