@auth
    @if (auth()->user()->isAdmin())
        <li class="nav-item"><a class="nav-link" href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    @endif
@endauth
