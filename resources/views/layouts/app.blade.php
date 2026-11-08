<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Church CMIS · PCEA Imani</title>
    <link href="{{ asset('vendor/mdb/css/mdb.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/mdb/css/admin.css') }}" rel="stylesheet">
    @stack('styles')
    <link href="{{ asset('church-admin.css') }}" rel="stylesheet">
</head>
<body class="{{ auth()->check() ? 'staff-layout' : 'guest-layout' }}">
<a class="skip-link" href="#main-content">Skip to content</a>
<svg xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true">
    <symbol id="icon-dashboard" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></symbol>
    <symbol id="icon-members" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 5a3 3 0 0 1 0 6M18 14a5 5 0 0 1 3 4v3"/></symbol>
    <symbol id="icon-giving" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M6 12h1M17 12h1"/></symbol>
    <symbol id="icon-communications" viewBox="0 0 24 24"><path d="M21 11a8 8 0 0 1-8 8H7l-4 3V11a9 9 0 0 1 18 0Z"/><path d="M7 9h10M7 13h7"/></symbol>
    <symbol id="icon-reports" viewBox="0 0 24 24"><path d="M4 3v18h17M9 16v-5M14 16V7M19 16V4"/></symbol>
</svg>
<header>
    @auth
        <nav id="sidebarMenu" class="collapse d-lg-block sidebar bg-white" aria-label="Main navigation">
            <div class="sidebar-sticky">
                <div class="nav-section mx-4 mt-4 mb-3">PARISH MANAGEMENT</div>
                <div class="list-group list-group-flush mx-3">
                    @foreach([
                        ['route' => 'dashboard', 'match' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'permission' => 'dashboard.view'],
                        ['route' => 'members.index', 'match' => 'members.*', 'label' => 'Members', 'icon' => 'members', 'permission' => 'members.view'],
                        ['route' => 'givings.index', 'match' => 'givings.*', 'label' => 'Giving', 'icon' => 'giving', 'permission' => 'givings.view'],
                        ['route' => 'reports.index', 'match' => 'reports.*', 'label' => 'Reports', 'icon' => 'reports', 'permission' => 'reports.view'],
                        ['route' => 'communications.index', 'match' => 'communications.*', 'label' => 'Communications', 'icon' => 'communications', 'permission' => 'communications.view'],
                        ['route' => 'users.index', 'match' => 'users.*', 'label' => 'Users', 'icon' => 'members', 'permission' => 'access.manage'],
                        ['route' => 'roles.index', 'match' => 'roles.*', 'label' => 'Roles & access', 'icon' => 'dashboard', 'permission' => 'access.manage'],
                    ] as $link)
                        @can($link['permission'])
                        <a href="{{ route($link['route']) }}" class="list-group-item list-group-item-action py-3 {{ request()->routeIs($link['match']) ? 'active' : '' }}" data-mdb-ripple-init @if(request()->routeIs($link['match'])) aria-current="page" @endif>
                            <svg class="nav-icon" aria-hidden="true"><use href="#icon-{{ $link['icon'] }}"/></svg>
                            <span>{{ $link['label'] }}</span>
                        </a>
                        @endcan
                    @endforeach
                </div>
                @can('settings.manage')
                <div class="nav-section mx-4 mt-4 mb-2">SETTINGS</div>
                <div class="list-group list-group-flush mx-3">
                    @foreach(['districts' => 'Districts', 'groups' => 'Groups', 'giving-types' => 'Giving types'] as $catalog => $label)
                    <a href="{{ route('catalogs.index', $catalog) }}" class="list-group-item list-group-item-action py-2 {{ request()->route('catalog') === $catalog ? 'active' : '' }}" @if(request()->route('catalog') === $catalog) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                </div>
                @endcan
                <div class="mx-4 mt-5 small text-muted">PCEA Kinoo Parish Imani<br><span class="small">Church management system</span></div>
            </div>
        </nav>
    @endauth
    <nav id="main-navbar" class="navbar navbar-expand-lg navbar-light bg-white fixed-top" aria-label="Account navigation">
        <div class="container-fluid px-3 px-lg-4">
            @auth
                <button class="navbar-toggler me-2" type="button" data-mdb-collapse-init data-mdb-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
                    <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
                </button>
            @endauth
            <a class="navbar-brand d-flex align-items-center gap-2" href="{{ auth()->check() ? route('home') : route('login') }}">
                <span class="brand-mark" aria-hidden="true">✝</span>
                <span><strong>CHURCH CMIS</strong><span class="d-block brand-subtitle text-muted">PCEA KINOO PARISH IMANI</span></span>
            </a>
            @auth
                <div class="d-flex align-items-center gap-3 ms-auto">
                    <span class="account-name d-none d-sm-inline">{{ auth()->user()->name }}<small class="d-block text-muted">{{ auth()->user()->role?->name ?? 'Unassigned role' }}</small></span>
                    <form action="{{ route('logout') }}" method="post">
                        @csrf
                        <button class="btn btn-outline-primary btn-sm" type="submit" data-mdb-ripple-init>Logout</button>
                    </form>
                </div>
            @endauth
        </div>
    </nav>
</header>
<main id="main-content" tabindex="-1">
    <div class="container-fluid page-content">
        @if(session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger" role="alert"><ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul></div>
        @endif
        @yield('content')
    </div>
</main>
<script src="{{ asset('vendor/mdb/js/mdb.umd.min.js') }}" defer></script>
@stack('scripts')
</body>
</html>
