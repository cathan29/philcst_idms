<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ID Monitoring') &middot; PhilCST IDMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&family=Plus+Jakarta+Sans:wght@600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen antialiased">
    @php
        $user = auth()->user();
        $isReleasedView = request()->routeIs('production.index') && request('status') === 'released';
        $isPrintedView = request()->routeIs('production.*') && !$isReleasedView;
    @endphp

    <a href="#mainContent" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-[#5B21B6] focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-white">
        Skip to main content
    </a>

    <div class="min-h-screen">
        {{-- Mobile backdrop --}}
        <div id="sidebarBackdrop" class="fixed inset-0 z-30 hidden bg-[#101828]/45 lg:hidden" aria-hidden="true"></div>

        {{-- Hover-Retractable Royal Purple PHILCST Sidebar --}}
        <aside id="appSidebar" aria-label="Application sidebar"
            class="app-sidebar fixed inset-y-0 left-0 z-40 flex -translate-x-full flex-col lg:translate-x-0">

            <div class="flex h-16 items-center justify-between gap-2 border-b border-white/12 px-4">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 min-w-0" aria-label="PHILCST IDMS Dashboard">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white p-1 shadow-sm">
                        <img src="{{ asset('images/philcst-seal.png') }}" alt="Philippine College of Science and Technology seal" class="h-full w-full object-contain">
                    </span>
                    <span class="sidebar-brand-copy min-w-0">
                        <span class="block truncate text-[15px] font-bold tracking-tight text-white" style="font-family: var(--font-display)">PHILCST IDMS</span>
                        <span class="block truncate text-[11px] font-medium text-purple-200/75">ID Monitoring System</span>
                    </span>
                </a>
                <button id="sidebarPinButton" type="button" aria-label="Pin expanded sidebar" aria-pressed="false" title="Pin sidebar open"
                    class="sidebar-pin-btn hidden h-7 w-7 shrink-0 items-center justify-center rounded-md text-purple-200/75 hover:bg-white/12 hover:text-white lg:inline-flex">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 17v5M5 3h14l-3 6v4l2 2H6l2-2V9L5 3Z"/>
                    </svg>
                </button>
            </div>

            <div class="flex-1 overflow-x-hidden overflow-y-auto px-3 py-4">
                <p class="sidebar-group-label">Monitoring</p>
                <nav class="sidebar-nav flex flex-col gap-1" aria-label="Primary navigation">
                    <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}" title="Dashboard">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
                        <span class="nav-label">Dashboard</span>
                    </a>

                    @if($user->hasPermission('students.view'))
                        <a class="{{ request()->routeIs('students.*') ? 'active' : '' }}" href="{{ route('students.index') }}" title="Student Records">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            <span class="nav-label">Student Records</span>
                        </a>
                    @endif

                    @if($user->hasPermission('imports.manage'))
                        <a class="{{ request()->routeIs('imports.*') ? 'active' : '' }}" href="{{ route('imports.students.create') }}" title="Import CSV">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4m0 0-4 4m4-4 4 4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
                            <span class="nav-label">Import CSV</span>
                        </a>
                    @endif

                    @if($user->hasPermission('cards.view'))
                        <a class="{{ $isPrintedView ? 'active' : '' }}" href="{{ route('production.index') }}" title="Printed IDs">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v7H6z"/></svg>
                            <span class="nav-label">Printed IDs</span>
                        </a>

                        <a class="{{ $isReleasedView ? 'active' : '' }}" href="{{ route('production.index', ['status' => 'released']) }}" title="Released IDs">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 12 2 2 4-4"/><rect x="3" y="4" width="18" height="16" rx="2"/></svg>
                            <span class="nav-label">Released IDs</span>
                        </a>

                        <a class="{{ request()->routeIs('scanner.*') ? 'active' : '' }}" href="{{ route('scanner.index') }}" title="ID Verification">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 8V5a2 2 0 0 1 2-2h3M16 3h3a2 2 0 0 1 2 2v3M21 16v3a2 2 0 0 1-2 2h-3M8 21H5a2 2 0 0 1-2-2v-3M7 12h10"/></svg>
                            <span class="nav-label">Verification</span>
                        </a>
                    @endif

                    @if($user->hasPermission('reports.view'))
                        <a class="{{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}" title="Reports">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V5m0 14h16M8 16v-4m4 4V8m4 8v-7m4 7V4"/></svg>
                            <span class="nav-label">Reports</span>
                        </a>
                    @endif
                </nav>

                @if($user->hasPermission('users.manage') || $user->hasPermission('settings.manage') || $user->hasPermission('audit.view'))
                    <p class="sidebar-group-label mt-6">Administration</p>
                    <nav class="sidebar-nav flex flex-col gap-1" aria-label="Administration navigation">
                        @if($user->hasPermission('settings.manage'))
                            <a class="{{ request()->routeIs('academic-terms.*') ? 'active' : '' }}" href="{{ route('academic-terms.index') }}" title="Academic Settings">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>
                                <span class="nav-label">Academic Settings</span>
                            </a>
                        @endif
                        @if($user->hasPermission('users.manage'))
                            <a class="{{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}" title="Users">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6m-3-3h6"/></svg>
                                <span class="nav-label">Users</span>
                            </a>
                        @endif
                        @if($user->hasPermission('audit.view'))
                            <a class="{{ request()->routeIs('logs.*') ? 'active' : '' }}" href="{{ route('logs.index') }}" title="Scan Activity">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/></svg>
                                <span class="nav-label">Scan Activity</span>
                            </a>
                        @endif
                    </nav>
                @endif
            </div>

            <div class="border-t border-white/12 p-3">
                <div class="flex items-center justify-between gap-2.5 rounded-xl bg-white/8 px-2 py-2">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#6D28D9] text-xs font-semibold text-white ring-1 ring-white/25">
                            {{ strtoupper(substr($user->name, 0, 1)) }}
                        </div>
                        <div class="sidebar-user-copy min-w-0">
                            <p class="truncate text-[13px] font-semibold text-white" title="{{ $user->name }}">{{ $user->name }}</p>
                            <p class="truncate text-[11px] text-purple-200/75" title="{{ $user->email }}">{{ $user->email }}</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="sidebar-logout-form shrink-0">
                        @csrf
                        <button type="submit" title="Log out" aria-label="Log out"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-purple-200/80 transition-colors hover:bg-white/12 hover:text-white">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M10 17l5-5-5-5M15 12H3M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        {{-- Main content area with subtle PHILCST campus building backdrop --}}
        <div class="app-main-shell campus-backdrop flex min-h-screen min-w-0 flex-col lg:pl-[72px]">
            <header class="sticky top-0 z-20 flex h-16 items-center justify-between gap-4 border-b border-[#EAECF0] bg-white/95 px-4 backdrop-blur-xs sm:px-6 lg:px-8">
                <div class="flex items-center gap-3 min-w-0">
                    <button id="mobileMenuButton" type="button" aria-controls="appSidebar" aria-expanded="false" aria-label="Open navigation"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[#D0D5DD] bg-white text-[#344054] hover:bg-[#F9FAFB] lg:hidden">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true">
                            <path d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>

                    <div class="flex items-center gap-2 text-sm min-w-0">
                        <span class="hidden text-[#98A2B3] sm:inline">PHILCST</span>
                        <span class="hidden text-[#D0D5DD] sm:inline">/</span>
                        <span class="truncate font-semibold text-[#101828]">@yield('title', 'ID Monitoring')</span>
                    </div>
                </div>

                <div class="flex items-center gap-2.5 shrink-0 text-[13px]" role="status" aria-label="Active academic term">
                    @if($activeAcademicTerm)
                        <span class="inline-flex items-center gap-2 rounded-full border border-[#EAECF0] bg-[#F9FAFB] px-3 py-1 font-medium text-[#344054] num">
                            <span class="h-1.5 w-1.5 rounded-full bg-[#12B76A]" aria-hidden="true"></span>
                            <span>AY {{ $activeAcademicTerm->academicYear?->name ?? '-' }} &middot; {{ $activeAcademicTerm->name }}</span>
                        </span>
                        @if($user->hasPermission('settings.manage'))
                            <a href="{{ route('academic-terms.index') }}" class="text-xs font-medium text-[#5B21B6] hover:underline">Settings</a>
                        @endif
                    @else
                        <span class="inline-flex items-center gap-2 rounded-full border border-[#FEDF89] bg-[#FFFAEB] px-3 py-1 text-xs font-medium text-[#B54708]">
                            <span class="h-1.5 w-1.5 rounded-full bg-[#F79009]" aria-hidden="true"></span>
                            <span>No active academic term</span>
                            @if($user->hasPermission('settings.manage'))
                                <a href="{{ route('academic-terms.index') }}" class="ml-1 font-semibold underline">Configure</a>
                            @endif
                        </span>
                    @endif
                </div>
            </header>

            <main id="mainContent" class="mx-auto w-full max-w-[1480px] flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-7">
                @if(session('success'))
                    <div class="alert-banner success" role="status">{{ session('success') }}</div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>

    @stack('scripts')
    <script>
        (() => {
            const sidebar = document.getElementById('appSidebar');
            const pinButton = document.getElementById('sidebarPinButton');
            const mobileButton = document.getElementById('mobileMenuButton');
            const backdrop = document.getElementById('sidebarBackdrop');
            const desktopQuery = window.matchMedia('(min-width: 1024px)');
            let retractTimer = null;
            let pinned = localStorage.getItem('philcst-sidebar-pinned') === 'true';

            const syncPin = () => {
                document.body.classList.toggle('sidebar-pinned', pinned && desktopQuery.matches);
                if (pinButton) {
                    pinButton.setAttribute('aria-pressed', String(pinned));
                    pinButton.setAttribute('aria-label', pinned ? 'Unpin sidebar' : 'Pin expanded sidebar');
                    pinButton.title = pinned ? 'Unpin sidebar' : 'Pin sidebar open';
                }
            };

            const expandHover = () => {
                if (!desktopQuery.matches || pinned) return;
                clearTimeout(retractTimer);
                document.body.classList.add('sidebar-hover');
            };

            const retractHover = () => {
                if (!desktopQuery.matches || pinned) return;
                clearTimeout(retractTimer);
                retractTimer = setTimeout(() => {
                    if (!sidebar.contains(document.activeElement)) {
                        document.body.classList.remove('sidebar-hover');
                    }
                }, 140);
            };

            sidebar?.addEventListener('pointerenter', expandHover);
            sidebar?.addEventListener('pointerleave', retractHover);
            sidebar?.addEventListener('focusin', expandHover);
            sidebar?.addEventListener('focusout', () => requestAnimationFrame(retractHover));

            pinButton?.addEventListener('click', () => {
                pinned = !pinned;
                localStorage.setItem('philcst-sidebar-pinned', String(pinned));
                if (!pinned) document.body.classList.remove('sidebar-hover');
                syncPin();
            });

            const setMobileOpen = (open) => {
                if (!sidebar || !backdrop || !mobileButton) return;
                sidebar.classList.toggle('-translate-x-full', !open);
                backdrop.classList.toggle('hidden', !open);
                mobileButton.setAttribute('aria-expanded', String(open));
            };

            mobileButton?.addEventListener('click', () => setMobileOpen(sidebar.classList.contains('-translate-x-full')));
            backdrop?.addEventListener('click', () => setMobileOpen(false));
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    setMobileOpen(false);
                    if (!pinned) document.body.classList.remove('sidebar-hover');
                }
            });
            desktopQuery.addEventListener('change', () => {
                setMobileOpen(false);
                document.body.classList.remove('sidebar-hover');
                syncPin();
            });
            syncPin();
        })();
    </script>
</body>
</html>
