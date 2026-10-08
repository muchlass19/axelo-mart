@extends('layouts.app')
@section('title', 'Dashboard Admin')

@section('content')
<h3 class="mb-3">Dashboard Admin</h3>

<div class="row g-3 mb-4">
    @foreach ([
        ['Total Penjualan', rupiah($allTime['total_revenue']), $allTime['total_orders'].' order dibayar'],
        ['Penjualan 30 Hari', rupiah($last30Days['total_revenue']), $last30Days['total_orders'].' order · '.$last30Days['items_sold'].' item'],
        ['Menunggu Pembayaran', $pendingOrders, 'order pending'],
        ['Jumlah Produk', $productCount, $lowStock->count().' stok menipis'],
    ] as [$label, $value, $hint])
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">{{ $label }}</div>
                    <div class="fs-4 fw-bold">{{ $value }}</div>
                    <div class="small text-muted">{{ $hint }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">🏆 Produk Terlaris</div>
            <table class="table mb-0">
                <thead><tr><th>Produk</th><th class="text-end">Terjual</th><th class="text-end">Omzet</th></tr></thead>
                <tbody>
                @forelse ($bestSellers as $row)
                    <tr><td>{{ $row['product_name'] }}</td><td class="text-end">{{ $row['quantity_sold'] }}</td><td class="text-end">{{ rupiah($row['revenue']) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-muted">Belum ada penjualan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">⚠️ Stok Menipis (≤ {{ $threshold }})</div>
            <table class="table mb-0">
                <thead><tr><th>Produk</th><th>Status</th><th class="text-end">Stok</th></tr></thead>
                <tbody>
                @forelse ($lowStock as $product)
                    <tr>
                        <td><a href="{{ route('admin.products.edit', $product) }}">{{ $product->name }}</a></td>
                        <td>{{ $product->status }}</td>
                        <td class="text-end"><span class="badge text-bg-{{ $product->stock ? 'warning' : 'danger' }}">{{ $product->stock }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-muted">Semua stok aman.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between">
                <span>🧾 Pesanan Terbaru</span><a href="{{ route('admin.orders.index') }}" class="small">Lihat semua</a>
            </div>
            <table class="table mb-0">
                <thead><tr><th>No. Order</th><th>Customer</th><th>Tanggal</th><th>Status</th><th class="text-end">Total</th></tr></thead>
                <tbody>
                @forelse ($recentOrders as $order)
                    <tr>
                        <td><a href="{{ route('admin.orders.show', $order) }}">{{ $order->order_number }}</a></td>
                        <td>{{ $order->customer_name }}</td>
                        <td>{{ $order->created_at->format('d M Y H:i') }}</td>
                        <td>@include('partials.order-status')</td>
                        <td class="text-end">{{ rupiah($order->total_amount) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted">Belum ada pesanan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
