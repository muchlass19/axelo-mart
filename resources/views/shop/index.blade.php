@extends('layouts.app')
@section('title', 'Katalog')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h3 class="mb-0">Katalog Produk</h3>
    <form class="d-flex gap-2">
        <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari produk...">
        <button class="btn btn-outline-secondary">Cari</button>
    </form>
</div>

<div class="row g-3">
    @forelse ($products as $product)
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card h-100 shadow-sm">
                <a href="{{ route('shop.show', $product) }}"><img src="{{ $product->coverUrl() }}" class="card-img-top product-thumb" alt="{{ $product->name }}"></a>
                <div class="card-body d-flex flex-column">
                    <a href="{{ route('shop.show', $product) }}" class="text-decoration-none text-dark fw-semibold">{{ $product->name }}</a>
                    <div class="text-primary fw-bold my-1">{{ rupiah($product->price) }}</div>
                    <div class="small text-muted mb-2">Stok: {{ $product->stock }}</div>
                    @unless (auth()->user()?->isAdmin())
                        <form method="POST" action="{{ route('cart.add', $product) }}" class="mt-auto">@csrf
                            <button class="btn btn-sm btn-primary w-100">+ Keranjang</button>
                        </form>
                    @endunless
                </div>
            </div>
        </div>
    @empty
        <div class="col-12"><div class="alert alert-light">Produk tidak ditemukan.</div></div>
    @endforelse
</div>
<div class="mt-4">{{ $products->links() }}</div>
@endsection
