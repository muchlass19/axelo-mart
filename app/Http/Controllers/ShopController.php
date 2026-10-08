<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function index(Request $request): View
    {
        $products = Product::available()
            ->with('images')
            ->when($request->query('q'), fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('shop.index', compact('products'));
    }

    public function show(Product $product): View
    {
        abort_unless($product->isActive(), 404);
        $product->load('images');

        return view('shop.show', compact('product'));
    }
}
