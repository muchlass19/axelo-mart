@extends('layouts.app')
@section('title', 'Pesanan')

@section('content')
<h3 class="mb-3">Pesanan</h3>

<form class="row g-2 mb-3">
    <div class="col-md-6"><input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari no. order / nama customer..."></div>
    <div class="col-md-3">
        <select name="status" class="form-select">
            <option value="">Semua status</option>
            @foreach (\App\Models\Order::STATUSES as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3"><button class="btn btn-outline-secondary w-100">Filter</button></div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>No. Order</th><th>Customer</th><th>Tanggal</th><th class="text-end">Item</th><th class="text-end">Total</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($orders as $order)
                <tr>
                    <td>{{ $order->order_number }}</td>
                    <td>{{ $order->customer_name }}<div class="small text-muted">{{ $order->user?->email }}</div></td>
                    <td>{{ $order->created_at->format('d M Y H:i') }}</td>
                    <td class="text-end">{{ $order->items_count }}</td>
                    <td class="text-end">{{ rupiah($order->total_amount) }}</td>
                    <td>@include('partials.order-status')</td>
                    <td class="text-end"><a href="{{ route('admin.orders.show', $order) }}" class="btn btn-sm btn-outline-primary">Detail</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-muted">Belum ada pesanan.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $orders->links() }}</div>
@endsection
