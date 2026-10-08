<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\MidtransService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = $request->user()->orders()->withCount('items')->latest()->paginate(10);

        return view('orders.index', compact('orders'));
    }

    public function show(Request $request, Order $order, MidtransService $midtrans): View
    {
        $this->authorizeOwner($request, $order);
        $order->load('items');

        return view('orders.show', [
            'order' => $order,
            'midtrans' => $midtrans,
        ]);
    }

    /** Buat Snap token (jika belum ada) lalu buka popup pembayaran. */
    public function pay(Request $request, Order $order, MidtransService $midtrans): RedirectResponse
    {
        $this->authorizeOwner($request, $order);

        if ($order->status !== Order::STATUS_PENDING) {
            return back()->with('error', 'Pesanan ini tidak bisa dibayar (status: '.$order->statusLabel().').');
        }
        if (! $midtrans->isConfigured()) {
            return back()->with('error', 'Pembayaran Midtrans belum dikonfigurasi. Isi MIDTRANS_SERVER_KEY dan MIDTRANS_CLIENT_KEY di .env.');
        }

        try {
            $midtrans->snapToken($order);
        } catch (Throwable $e) {
            Log::error('Gagal membuat Snap token', ['order' => $order->order_number, 'error' => $e->getMessage()]);

            return back()->with('error', 'Gagal menghubungi Midtrans: '.$e->getMessage());
        }

        return back()->with('open_snap', true);
    }

    /** Sinkronkan status dari Midtrans Transaction Status API (pengganti webhook saat lokal). */
    public function checkStatus(Request $request, Order $order, MidtransService $midtrans): RedirectResponse
    {
        $this->authorizeOwner($request, $order);

        if (! $midtrans->isConfigured()) {
            return back()->with('error', 'Midtrans belum dikonfigurasi, status tidak bisa dicek.');
        }

        try {
            $data = $midtrans->fetchStatus($order);
        } catch (Throwable $e) {
            if ($e->getCode() === 404) {
                return back()->with('info', 'Transaksi belum ada di Midtrans. Klik "Bayar Sekarang" dan pilih metode pembayaran dulu.');
            }
            Log::error('Gagal cek status Midtrans', ['order' => $order->order_number, 'error' => $e->getMessage()]);

            return back()->with('error', 'Gagal cek status ke Midtrans: '.$e->getMessage());
        }

        $midtrans->applyTransaction($order, $data);

        return back()->with('success', 'Status Midtrans: '.($data['transaction_status'] ?? '-').'. Status pesanan: '.$order->fresh()->statusLabel().'.');
    }

    private function authorizeOwner(Request $request, Order $order): void
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 404);
    }
}
