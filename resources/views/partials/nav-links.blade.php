@auth
    @if (auth()->user()->isAdmin())
        <li class="nav-item"><a class="nav-link @if(request()->routeIs('admin.dashboard')) active @endif" href="{{ route('admin.dashboard') }}">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link @if(request()->routeIs('admin.products.*')) active @endif" href="{{ route('admin.products.index') }}">Produk</a></li>
        <li class="nav-item"><a class="nav-link @if(request()->routeIs('admin.orders.*')) active @endif" href="{{ route('admin.orders.index') }}">Pesanan</a></li>
    @endif
@endauth
