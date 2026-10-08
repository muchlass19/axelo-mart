@extends('layouts.app')
@section('title', 'Order '.$order->order_number)

@section('content')
<a href="{{ route('admin.orders.index') }}" class="small">&larr; Kembali ke daftar pesanan</a>
<h3 class="mb-3 mt-2">Order {{ $order->order_number }} @include('partials.order-status')</h3>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow-sm">@include('partials.order-items')</div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm mb-3">
            <div class="card-body small">
                <p class="mb-1"><strong>Customer:</strong> {{ $order->customer_name }} ({{ $order->user?->email }})</p>
                <p class="mb-1"><strong>HP:</strong> {{ $order->customer_phone }}</p>
                <p class="mb-1"><strong>Alamat:</strong> {{ $order->shipping_address }}</p>
                @if ($order->notes)<p class="mb-1"><strong>Catatan:</strong> {{ $order->notes }}</p>@endif
                <p class="mb-1"><strong>Dibuat:</strong> {{ $order->created_at->format('d M Y H:i') }}</p>
                <p class="mb-1"><strong>Dibayar:</strong> {{ $order->paid_at?->format('d M Y H:i') ?? '-' }}</p>
                <p class="mb-1"><strong>Metode bayar:</strong> {{ $order->payment_type ?? '-' }}</p>
                <p class="mb-0"><strong>Stok sudah dipotong:</strong> {{ $order->stock_deducted ? 'Ya' : 'Belum' }}</p>
            </div>
        </div>
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.orders.status', $order) }}">
                    @csrf @method('PATCH')
                    <label class="form-label fw-semibold">Ubah Status</label>
                    <select name="status" class="form-select mb-2">
                        @foreach (\App\Models\Order::STATUSES as $value => $label)
                            <option value="{{ $value }}" @selected($order->status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-primary w-100">Simpan Status</button>
                    <p class="small text-muted mt-2 mb-0">Status Dibayar/Dikirim/Selesai memotong stok (sekali). Status lain mengembalikan stok jika sebelumnya sudah dipotong.</p>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
