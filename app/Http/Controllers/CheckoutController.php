<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\MidtransService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CheckoutController extends Controller
{
    public function store(Request $request, MidtransService $midtrans): RedirectResponse
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'shipping_address' => ['required', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $items = CartController::items();
        if ($items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Keranjang masih kosong.');
        }

        // Cek ketersediaan. Stok BELUM dipotong di sini; dipotong saat pembayaran sukses.
        foreach ($items as $item) {
            $product = $item['product'];
            if (! $product->isActive() || $product->stock < $item['quantity']) {
                return redirect()->route('cart.index')
                    ->with('error', "Stok \"{$product->name}\" tidak mencukupi (tersisa {$product->stock}).");
            }
        }

        $order = DB::transaction(function () use ($request, $data, $items) {
            $order = $request->user()->orders()->create([
                ...$data,
                'order_number' => Order::generateNumber(),
                'status' => Order::STATUS_PENDING,
                'total_amount' => $items->sum('subtotal'),
            ]);

            foreach ($items as $item) {
                $order->items()->create([
                    'product_id' => $item['product']->id,
                    'product_name' => $item['product']->name,
                    'price' => $item['product']->price,
                    'quantity' => $item['quantity'],
                    'subtotal' => $item['subtotal'],
                ]);
            }

            return $order;
        });

        session()->forget('cart');

        if (! $midtrans->isConfigured()) {
            return redirect()->route('orders.show', $order)
                ->with('warning', 'Pesanan dibuat, tapi pembayaran Midtrans belum dikonfigurasi (MIDTRANS_SERVER_KEY / MIDTRANS_CLIENT_KEY kosong).');
        }

        try {
            $midtrans->snapToken($order);
        } catch (Throwable $e) {
            Log::error('Gagal membuat Snap token', ['order' => $order->order_number, 'error' => $e->getMessage()]);

            return redirect()->route('orders.show', $order)
                ->with('warning', 'Pesanan dibuat, tapi gagal menghubungi Midtrans. Coba klik "Bayar Sekarang" lagi nanti.');
        }

        return redirect()->route('orders.show', $order)
            ->with('success', 'Pesanan dibuat. Silakan selesaikan pembayaran.')
            ->with('open_snap', true);
    }
}
