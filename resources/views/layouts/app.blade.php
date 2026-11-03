<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ID Monitoring') Â· PhilCST IDMS</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="has-navigation-rail">
    <div class="app-shell">
        <aside class="sidebar" id="appSidebar" aria-label="Application sidebar">
            <div class="sidebar-campus" aria-hidden="true"></div>
            <div class="sidebar-brand-row">
            <a href="{{ route('dashboard') }}" class="brand" aria-label="PhilCST IDMS overview">
                <img class="brand-logo" src="{{ asset('images/philcst-seal.png') }}" alt="Philippine College of Science and Technology seal">
                <span><strong>PhilCST IDMS</strong><small>ID Monitoring &amp; Issuance<br>Management</small></span>
            </a>
            <button class="sidebar-pin" id="sidebarPin" type="button" aria-label="Pin expanded sidebar" aria-pressed="false" title="Pin sidebar">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 17v5M5 3h14l-3 6v4l2 2H6l2-2V9L5 3Z"/></svg>
            </button>
            </div>
            <nav class="sidebar-nav" aria-label="Primary navigation">
                <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}" data-tooltip="Overview" title="Overview"><span class="nav-icon"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg></span><span class="nav-label">Overview</span></a>
                @if(auth()->user()->hasPermission('students.view'))<a class="{{ request()->routeIs('students.*') ? 'active' : '' }}" href="{{ route('students.index') }}" data-tooltip="Student Registry" title="Student Registry"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></span><span class="nav-label">Student Registry</span></a>@endif
                @if(auth()->user()->hasPermission('imports.manage'))<a class="{{ request()->routeIs('imports.*') ? 'active' : '' }}" href="{{ route('imports.students.create') }}" data-tooltip="CSV Imports" title="CSV Imports"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M4 17V5a2 2 0 0 1 2-2h8l6 6v8a2 2 0 0 1-2 2h-2"/><path d="M14 3v6h6M3 14v7m0 0 3-3m-3 3-3-3"/></svg></span><span class="nav-label">CSV Imports</span></a>@endif
                @if(auth()->user()->hasPermission('cards.view'))<a class="{{ request()->routeIs('production.*') ? 'active' : '' }}" href="{{ route('production.index') }}" data-tooltip="ID Production" title="ID Production"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v7H6z"/></svg></span><span class="nav-label">ID Production</span></a>@endif
                <span class="nav-disabled" title="QR scanner station is not configured yet"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M4 4h5v5H4zM15 4h5v5h-5zM4 15h5v5H4zM15 15h2m3 0v5h-5m0-5v2m5-2h0"/></svg></span>QR Scanner</span>
                <span class="nav-disabled" title="ID releasing module is not available yet"><span class="nav-icon"><svg viewBox="0 0 24 24"><rect x="4" y="3" width="16" height="18" rx="2"/><circle cx="12" cy="9" r="3"/><path d="M8 16h8"/></svg></span>ID Releasing</span>
                <span class="nav-disabled" title="Reports module is not available yet"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M4 19V5m0 14h16M8 16v-4m4 4V8m4 8v-7m4 7V4"/></svg></span>Reports &amp; Analytics</span>
            </nav>
            <div class="sidebar-secondary"><span>Administration</span><a class="{{ request()->routeIs('holders.*') ? 'active' : '' }}" href="{{ route('holders.index') }}" data-tooltip="Access Directory" title="Access Directory"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M12 3 5 6v5c0 4.6 2.8 8.1 7 10 4.2-1.9 7-5.4 7-10V6l-7-3Z"/><path d="m9 12 2 2 4-5"/></svg></span><span class="nav-label">Access Directory</span></a><a class="{{ request()->routeIs('logs.*') ? 'active' : '' }}" href="{{ route('logs.index') }}" data-tooltip="Scan Activity" title="Scan Activity"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/></svg></span><span class="nav-label">Scan Activity</span></a>@if(auth()->user()->hasPermission('settings.manage'))<a class="{{ request()->routeIs('academic-terms.*') ? 'active' : '' }}" href="{{ route('academic-terms.index') }}" data-tooltip="Academic Terms" title="Academic Terms"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M7 3v3m10-3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Z"/></svg></span><span class="nav-label">Academic Terms</span></a>@endif</div>
            <div class="sidebar-user">
                <div class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
                <div class="sidebar-user-copy"><strong title="{{ auth()->user()->name }}">{{ auth()->user()->name }}</strong><small title="{{ auth()->user()->email }}">{{ auth()->user()->email }}</small></div>
                <form method="POST" action="{{ route('logout') }}">@csrf<button title="Log out" aria-label="Log out"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/></svg><span>Log out</span></button></form>
            </div>
        </aside>
        <button class="mobile-sidebar-backdrop" id="sidebarBackdrop" type="button" aria-label="Close navigation"></button>
        <main class="main">
            <button class="mobile-menu-button" id="mobileMenuButton" type="button" aria-controls="appSidebar" aria-expanded="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg><span class="sr-only">Open navigation</span></button>
            @if(!request()->routeIs('dashboard', 'students.index', 'imports.*', 'production.*'))
                <header class="topbar"><div><p class="eyebrow">PhilCST ID Monitoring &amp; Issuance</p><h1>@yield('heading', 'Dashboard')</h1></div><div class="live-clock" id="liveClock"></div></header>
            @endif
            <div class="content">
                @if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif
                @yield('content')
            </div>
        </main>
    </div>
    @if(!request()->routeIs('dashboard', 'students.index', 'imports.*', 'production.*'))<script>const clock=document.getElementById('liveClock');const tick=()=>{if(clock)clock.textContent=new Intl.DateTimeFormat('en-PH',{dateStyle:'medium',timeStyle:'medium',timeZone:'Asia/Manila'}).format(new Date())};tick();setInterval(tick,1000);</script>@endif
    @stack('scripts')
    <script>
        (() => {
            const sidebar = document.getElementById('appSidebar');
            const pin = document.getElementById('sidebarPin');
            const mobileButton = document.getElementById('mobileMenuButton');
            const backdrop = document.getElementById('sidebarBackdrop');
            const mobileQuery = window.matchMedia('(max-width: 760px)');
            let closeTimer;
            let pinned = localStorage.getItem('idms-sidebar-pinned') === 'true';

            const sync = () => {
                document.body.classList.toggle('sidebar-pinned', pinned && !mobileQuery.matches);
                pin.setAttribute('aria-pressed', String(pinned));
                pin.setAttribute('aria-label', pinned ? 'Unpin sidebar' : 'Pin expanded sidebar');
                pin.title = pinned ? 'Unpin sidebar' : 'Pin sidebar';
            };
            const open = () => { clearTimeout(closeTimer); document.body.classList.add('sidebar-expanded'); };
            const close = (immediate = false) => {
                if (pinned || mobileQuery.matches || sidebar.contains(document.activeElement)) return;
                clearTimeout(closeTimer);
                closeTimer = setTimeout(() => document.body.classList.remove('sidebar-expanded'), immediate ? 0 : 180);
            };
            const closeMobile = () => {
                document.body.classList.remove('sidebar-mobile-open');
                mobileButton.setAttribute('aria-expanded', 'false');
            };

            sidebar.addEventListener('pointerenter', () => { if (!mobileQuery.matches) open(); });
            sidebar.addEventListener('pointerleave', () => close());
            sidebar.addEventListener('focusin', () => { if (!mobileQuery.matches) open(); });
            sidebar.addEventListener('focusout', () => requestAnimationFrame(() => close()));
            pin.addEventListener('click', () => {
                pinned = !pinned;
                localStorage.setItem('idms-sidebar-pinned', String(pinned));
                if (pinned) open();
                sync();
            });
            mobileButton.addEventListener('click', () => {
                const openState = document.body.classList.toggle('sidebar-mobile-open');
                mobileButton.setAttribute('aria-expanded', String(openState));
                if (openState) sidebar.querySelector('a,button')?.focus();
            });
            backdrop.addEventListener('click', closeMobile);
            document.addEventListener('keydown', event => {
                if (event.key !== 'Escape') return;
                if (document.body.classList.contains('sidebar-mobile-open')) closeMobile();
                else if (!pinned) { document.body.classList.remove('sidebar-expanded'); document.activeElement?.blur(); }
            });
            mobileQuery.addEventListener('change', () => { closeMobile(); document.body.classList.remove('sidebar-expanded'); sync(); });
            sync();
        })();
    </script>
</body>
</html>
