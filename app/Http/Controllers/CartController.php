<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Keranjang disimpan di session: ['cart' => [product_id => qty]].
 */
class CartController extends Controller
{
    public function index(Request $request): View
    {
        $items = self::items();

        return view('cart.index', [
            'items' => $items,
            'total' => $items->sum('subtotal'),
            'user' => $request->user(),
        ]);
    }

    public function add(Request $request, Product $product): RedirectResponse
    {
        $qty = (int) ($request->validate(['quantity' => ['nullable', 'integer', 'min:1']])['quantity'] ?? 1);

        if (! $product->isActive() || $product->stock < 1) {
            return back()->with('error', 'Produk tidak tersedia.');
        }

        $cart = session('cart', []);
        $cart[$product->id] = min(($cart[$product->id] ?? 0) + $qty, $product->stock);
        session(['cart' => $cart]);

        return back()->with('success', "\"{$product->name}\" ditambahkan ke keranjang.");
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['quantities' => ['array'], 'quantities.*' => ['integer', 'min:0']]);
        $cart = session('cart', []);

        foreach ($data['quantities'] ?? [] as $productId => $qty) {
            if ($qty < 1) {
                unset($cart[$productId]);
            } elseif (isset($cart[$productId])) {
                $cart[$productId] = (int) $qty;
            }
        }
        session(['cart' => $cart]);

        return back()->with('success', 'Keranjang diperbarui.');
    }

    public function remove(Product $product): RedirectResponse
    {
        session()->forget("cart.{$product->id}");

        return back()->with('success', 'Produk dihapus dari keranjang.');
    }

    /** @return Collection<int, array{product: Product, quantity: int, subtotal: int}> */
    public static function items(): Collection
    {
        $cart = session('cart', []);

        return Product::with('images')->whereIn('id', array_keys($cart))->get()
            ->map(fn (Product $product) => [
                'product' => $product,
                'quantity' => (int) $cart[$product->id],
                'subtotal' => $product->price * (int) $cart[$product->id],
            ])
            ->values();
    }
}
