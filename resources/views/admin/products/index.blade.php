@extends('layouts.app')
@section('title', 'Produk')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Produk</h3>
    <a href="{{ route('admin.products.create') }}" class="btn btn-primary">+ Tambah Produk</a>
</div>

<form class="row g-2 mb-3">
    <div class="col-md-6"><input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari nama produk..."></div>
    <div class="col-md-3">
        <select name="status" class="form-select">
            <option value="">Semua status</option>
            <option value="active" @selected(request('status') === 'active')>Aktif</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
        </select>
    </div>
    <div class="col-md-3"><button class="btn btn-outline-secondary w-100">Filter</button></div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th></th><th>Nama</th><th class="text-end">Harga</th><th class="text-end">Stok</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
            @forelse ($products as $product)
                <tr>
                    <td><img src="{{ $product->coverUrl() }}" class="thumb-sm" alt=""></td>
                    <td>{{ $product->name }}<div class="small text-muted">{{ $product->images->count() }} gambar</div></td>
                    <td class="text-end">{{ rupiah($product->price) }}</td>
                    <td class="text-end">{{ $product->stock }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.products.toggle', $product) }}">@csrf @method('PATCH')
                            <button class="btn btn-sm {{ $product->isActive() ? 'btn-success' : 'btn-outline-secondary' }}" title="Klik untuk ubah status">
                                {{ $product->isActive() ? 'Aktif' : 'Nonaktif' }}
                            </button>
                        </form>
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                        <form method="POST" action="{{ route('admin.products.destroy', $product) }}" class="d-inline" onsubmit="return confirm('Hapus produk ini?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Hapus</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-muted">Belum ada produk.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $products->links() }}</div>
@endsection
