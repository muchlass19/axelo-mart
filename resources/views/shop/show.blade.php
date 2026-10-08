@extends('layouts.app')
@section('title', $product->name)

@section('content')
<a href="{{ route('home') }}" class="small">&larr; Kembali ke katalog</a>
<div class="row g-4 mt-1">
    <div class="col-md-6">
        @if ($product->images->isNotEmpty())
            <div id="gallery" class="carousel slide bg-white rounded shadow-sm">
                <div class="carousel-inner rounded">
                    @foreach ($product->images as $image)
                        <div class="carousel-item @if($loop->first) active @endif">
                            <img src="{{ $image->url() }}" class="d-block w-100 product-thumb" alt="{{ $product->name }}">
                        </div>
                    @endforeach
                </div>
                @if ($product->images->count() > 1)
                    <button class="carousel-control-prev" type="button" data-bs-target="#gallery" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span></button>
                    <button class="carousel-control-next" type="button" data-bs-target="#gallery" data-bs-slide="next"><span class="carousel-control-next-icon"></span></button>
                @endif
            </div>
            <div class="d-flex gap-2 mt-2">
                @foreach ($product->images as $image)
                    <img src="{{ $image->url() }}" class="thumb-sm" role="button" data-bs-target="#gallery" data-bs-slide-to="{{ $loop->index }}" alt="">
                @endforeach
            </div>
        @else
            <div class="product-thumb rounded d-flex align-items-center justify-content-center text-muted">Tidak ada gambar</div>
        @endif
    </div>
    <div class="col-md-6">
        <h2>{{ $product->name }}</h2>
        <div class="fs-3 text-primary fw-bold mb-2">{{ rupiah($product->price) }}</div>
        <p class="mb-3">
            @if ($product->stock > 0)
                <span class="badge text-bg-success">Stok tersedia: {{ $product->stock }}</span>
            @else
                <span class="badge text-bg-danger">Stok habis</span>
            @endif
        </p>
        <p style="white-space: pre-line">{{ $product->description }}</p>

        @if (auth()->user()?->isAdmin())
            <div class="alert alert-info">Anda login sebagai admin. Login sebagai customer untuk berbelanja.</div>
        @elseif ($product->stock > 0)
            <form method="POST" action="{{ route('cart.add', $product) }}" class="d-flex gap-2" style="max-width: 320px">
                @csrf
                <input type="number" name="quantity" value="1" min="1" max="{{ $product->stock }}" class="form-control" style="max-width: 100px">
                <button class="btn btn-primary flex-grow-1">Tambah ke Keranjang</button>
            </form>
        @endif
    </div>
</div>
@endsection
