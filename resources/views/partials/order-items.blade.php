<table class="table mb-0">
    <thead><tr><th>Produk</th><th class="text-end">Harga</th><th class="text-end">Qty</th><th class="text-end">Subtotal</th></tr></thead>
    <tbody>
    @foreach ($order->items as $item)
        <tr>
            <td>{{ $item->product_name }}</td>
            <td class="text-end">{{ rupiah($item->price) }}</td>
            <td class="text-end">{{ $item->quantity }}</td>
            <td class="text-end">{{ rupiah($item->subtotal) }}</td>
        </tr>
    @endforeach
    </tbody>
    <tfoot><tr><th colspan="3" class="text-end">Total</th><th class="text-end">{{ rupiah($order->total_amount) }}</th></tr></tfoot>
</table>
