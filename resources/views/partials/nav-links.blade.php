@if (auth()->user()?->isAdmin())
    <li class="nav-item"><a class="nav-link @if(request()->routeIs('admin.dashboard')) active @endif" href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="nav-item"><a class="nav-link @if(request()->routeIs('admin.products.*')) active @endif" href="{{ route('admin.products.index') }}">Produk</a></li>
    <li class="nav-item"><a class="nav-link @if(request()->routeIs('admin.orders.*')) active @endif" href="{{ route('admin.orders.index') }}">Pesanan</a></li>
    <li class="nav-item"><a class="nav-link" href="{{ route('home') }}">Lihat Toko</a></li>
@else
    <li class="nav-item"><a class="nav-link @if(request()->routeIs('home', 'shop.*')) active @endif" href="{{ route('home') }}">Katalog</a></li>
    @auth
        <li class="nav-item"><a class="nav-link @if(request()->routeIs('cart.*')) active @endif" href="{{ route('cart.index') }}">Keranjang <span class="badge text-bg-light">{{ count(session('cart', [])) }}</span></a></li>
        <li class="nav-item"><a class="nav-link @if(request()->routeIs('orders.*')) active @endif" href="{{ route('orders.index') }}">Pesanan Saya</a></li>
    @endauth
@endif
