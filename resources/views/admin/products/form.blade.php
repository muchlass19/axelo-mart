@extends('layouts.app')
@section('title', $product->exists ? 'Edit Produk' : 'Tambah Produk')

@section('content')
<h3 class="mb-3">{{ $product->exists ? 'Edit Produk' : 'Tambah Produk' }}</h3>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data"
                      action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}">
                    @csrf
                    @if ($product->exists) @method('PUT') @endif

                    <div class="mb-3">
                        <label class="form-label">Nama Produk</label>
                        <input type="text" name="name" value="{{ old('name', $product->name) }}" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Harga (Rp)</label>
                            <input type="number" name="price" min="0" value="{{ old('price', $product->price) }}" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Stok</label>
                            <input type="number" name="stock" min="0" value="{{ old('stock', $product->stock ?? 0) }}" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active" @selected(old('status', $product->status) === 'active')>Aktif</option>
                            <option value="inactive" @selected(old('status', $product->status) === 'inactive')>Nonaktif</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="description" rows="5" class="form-control">{{ old('description', $product->description) }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tambah Gambar <span class="text-muted small">(bisa pilih banyak, maks 2MB/gambar)</span></label>
                        <input type="file" name="images[]" accept="image/*" multiple class="form-control">
                    </div>
                    <button class="btn btn-primary">Simpan</button>
                    <a href="{{ route('admin.products.index') }}" class="btn btn-link">Kembali</a>
                </form>
            </div>
        </div>
    </div>

    @if ($product->exists)
        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">Gambar Produk</div>
                <div class="card-body">
                    <div class="row g-2">
                        @forelse ($product->images as $image)
                            <div class="col-4 text-center">
                                <img src="{{ $image->url() }}" class="img-fluid rounded product-thumb mb-1" alt="">
                                <form method="POST" action="{{ route('admin.product-images.destroy', $image) }}" onsubmit="return confirm('Hapus gambar ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger w-100">Hapus</button>
                                </form>
                            </div>
                        @empty
                            <p class="text-muted mb-0">Belum ada gambar.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
