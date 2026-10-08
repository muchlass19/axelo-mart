@extends('layouts.app')
@section('title', 'Pesanan Saya')

@section('content')
<h3 class="mb-3">Pesanan Saya</h3>

<div class="card shadow-sm">
    <table class="table align-middle mb-0">
        <thead><tr><th>No. Order</th><th>Tanggal</th><th class="text-end">Item</th><th class="text-end">Total</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($orders as $order)
            <tr>
                <td>{{ $order->order_number }}</td>
                <td>{{ $order->created_at->format('d M Y H:i') }}</td>
                <td class="text-end">{{ $order->items_count }}</td>
                <td class="text-end">{{ rupiah($order->total_amount) }}</td>
                <td>@include('partials.order-status')</td>
                <td class="text-end"><a href="{{ route('orders.show', $order) }}" class="btn btn-sm btn-outline-primary">Detail</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-muted">Belum ada pesanan. <a href="{{ route('home') }}">Mulai belanja</a></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $orders->links() }}</div>
@endsection
