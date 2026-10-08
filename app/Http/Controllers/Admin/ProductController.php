<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $products = Product::with('images')
            ->when($request->query('q'), fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.products.index', compact('products'));
    }

    public function create(): View
    {
        return view('admin.products.form', ['product' => new Product(['status' => Product::STATUS_ACTIVE])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $product = Product::create($this->validated($request));
        $this->storeImages($request, $product);

        return redirect()->route('admin.products.index')->with('success', 'Produk berhasil ditambahkan.');
    }

    public function edit(Product $product): View
    {
        $product->load('images');

        return view('admin.products.form', compact('product'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $product->update($this->validated($request));
        $this->storeImages($request, $product);

        return redirect()->route('admin.products.edit', $product)->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        Storage::disk('public')->delete($product->images->pluck('path')->all());
        $product->delete();

        return redirect()->route('admin.products.index')->with('success', 'Produk dihapus.');
    }

    public function toggle(Product $product): RedirectResponse
    {
        $product->update([
            'status' => $product->isActive() ? Product::STATUS_INACTIVE : Product::STATUS_ACTIVE,
        ]);

        return back()->with('success', "Status \"{$product->name}\" sekarang {$product->status}.");
    }

    public function destroyImage(ProductImage $image): RedirectResponse
    {
        Storage::disk('public')->delete($image->path);
        $image->delete();

        return back()->with('success', 'Gambar dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'integer', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'status' => ['required', Rule::in([Product::STATUS_ACTIVE, Product::STATUS_INACTIVE])],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['image', 'max:2048'],
        ]);
    }

    private function storeImages(Request $request, Product $product): void
    {
        $order = (int) $product->images()->max('sort_order');

        foreach ($request->file('images', []) as $file) {
            $product->images()->create([
                'path' => $file->store('products', 'public'),
                'sort_order' => ++$order,
            ]);
        }
    }
}
