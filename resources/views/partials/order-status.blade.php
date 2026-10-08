@php
    $color = [
        'pending' => 'warning', 'paid' => 'primary', 'shipped' => 'info', 'completed' => 'success',
        'failed' => 'danger', 'expired' => 'secondary', 'cancelled' => 'dark',
    ][$order->status] ?? 'secondary';
@endphp
<span class="badge text-bg-{{ $color }}">{{ $order->statusLabel() }}</span>
