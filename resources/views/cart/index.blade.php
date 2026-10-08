@extends('layouts.app')
@section('title', 'Keranjang')

@section('content')
<h3 class="mb-3">Keranjang</h3>

@if ($items->isEmpty())
    <div class="alert alert-light">Keranjang masih kosong. <a href="{{ route('home') }}">Belanja sekarang</a></div>
@else
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <form method="POST" action="{{ route('cart.update') }}" id="cart-form">@csrf @method('PATCH')</form>
            <table class="table align-middle mb-0">
                <thead><tr><th>Produk</th><th style="width: 110px">Qty</th><th class="text-end">Subtotal</th><th></th></tr></thead>
                <tbody>
                @foreach ($items as $item)
                    @php($product = $item['product'])
                    <tr>
                        <td>
                            <div class="d-flex gap-2 align-items-center">
                                <img src="{{ $product->coverUrl() }}" class="thumb-sm" alt="">
                                <div>
                                    {{ $product->name }}
                                    <div class="small text-muted">{{ rupiah($product->price) }} · stok {{ $product->stock }}</div>
                                    @if (! $product->isActive() || $product->stock < $item['quantity'])
                                        <div class="small text-danger">Stok tidak mencukupi / produk nonaktif</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td><input type="number" form="cart-form" name="quantities[{{ $product->id }}]" value="{{ $item['quantity'] }}" min="0" class="form-control form-control-sm"></td>
                        <td class="text-end">{{ rupiah($item['subtotal']) }}</td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('cart.remove', $product) }}">@csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">&times;</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td><button form="cart-form" class="btn btn-sm btn-outline-secondary">Perbarui Keranjang</button></td>
                        <th class="text-end">Total</th><th class="text-end">{{ rupiah($total) }}</th><td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5>Checkout</h5>
                <form method="POST" action="{{ route('checkout') }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label">Nama Penerima</label>
                        <input type="text" name="customer_name" value="{{ old('customer_name', $user->name) }}" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">No. HP</label>
                        <input type="text" name="customer_phone" value="{{ old('customer_phone', $user->phone) }}" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Alamat Pengiriman</label>
                        <textarea name="shipping_address" rows="3" class="form-control" required>{{ old('shipping_address') }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Catatan <span class="text-muted">(opsional)</span></label>
                        <input type="text" name="notes" value="{{ old('notes') }}" class="form-control">
                    </div>
                    <button class="btn btn-success w-100">Buat Pesanan · {{ rupiah($total) }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endif
@endsection
