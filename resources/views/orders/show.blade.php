@extends('layouts.app')
@section('title', 'Pesanan '.$order->order_number)

@section('content')
<a href="{{ route('orders.index') }}" class="small">&larr; Pesanan saya</a>
<h3 class="mb-3 mt-2">Pesanan {{ $order->order_number }} @include('partials.order-status')</h3>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow-sm">@include('partials.order-items')</div>
        <div class="card shadow-sm mt-3">
            <div class="card-body small">
                <p class="mb-1"><strong>Penerima:</strong> {{ $order->customer_name }} ({{ $order->customer_phone }})</p>
                <p class="mb-1"><strong>Alamat:</strong> {{ $order->shipping_address }}</p>
                @if ($order->notes)<p class="mb-1"><strong>Catatan:</strong> {{ $order->notes }}</p>@endif
                <p class="mb-0"><strong>Dibuat:</strong> {{ $order->created_at->format('d M Y H:i') }}
                    @if ($order->paid_at) · <strong>Dibayar:</strong> {{ $order->paid_at->format('d M Y H:i') }} @endif
                    @if ($order->payment_type) · <strong>Metode:</strong> {{ $order->payment_type }} @endif
                </p>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5>Pembayaran</h5>
                @if (! $midtrans->isConfigured())
                    <div class="alert alert-warning small mb-0">
                        Pembayaran Midtrans belum dikonfigurasi. Isi <code>MIDTRANS_SERVER_KEY</code> dan <code>MIDTRANS_CLIENT_KEY</code> di <code>.env</code>.
                        (Admin tetap bisa mengubah status pesanan secara manual.)
                    </div>
                @elseif ($order->status === 'pending')
                    @if ($order->snap_token)
                        <button type="button" id="pay-button" class="btn btn-success w-100 mb-2">Bayar Sekarang</button>
                    @else
                        <form method="POST" action="{{ route('orders.pay', $order) }}">@csrf
                            <button class="btn btn-success w-100 mb-2">Bayar Sekarang</button>
                        </form>
                    @endif
                @endif

                @if ($midtrans->isConfigured() && $order->snap_token)
                    <form method="POST" action="{{ route('orders.check-status', $order) }}" id="check-status-form">@csrf
                        <button class="btn btn-outline-primary w-100">Cek Status Pembayaran</button>
                    </form>
                    <p class="small text-muted mt-2 mb-0">Sudah bayar tapi status belum berubah? Klik "Cek Status Pembayaran".</p>
                @elseif ($order->isPaid())
                    <p class="text-success mb-0">Pesanan sudah dibayar. Terima kasih!</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@if ($midtrans->isConfigured() && $order->status === 'pending' && $order->snap_token)
    @push('scripts')
        <script src="{{ $midtrans->snapJsUrl() }}" data-client-key="{{ $midtrans->clientKey() }}"></script>
        <script>
            const checkStatus = () => document.getElementById('check-status-form').submit();
            const openSnap = () => window.snap.pay(@json($order->snap_token), {
                onSuccess: checkStatus,
                onPending: checkStatus,
                onError: checkStatus,
            });
            document.getElementById('pay-button').addEventListener('click', openSnap);
            @if (session('open_snap'))
                window.addEventListener('load', openSnap);
            @endif
        </script>
    @endpush
@endif
