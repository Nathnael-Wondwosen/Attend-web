<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Finot | Admin Command Center')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { grotesk: ['Space Grotesk', 'Inter', 'system-ui'] },
                    colors: {
                        primary: '#7c3aed',
                        neon: '#22d3ee',
                        mint: '#34d399',
                        midnight: '#0b1021',
                        panel: 'rgba(255,255,255,0.06)',
                    },
                    boxShadow: {
                        glow: '0 10px 60px rgba(124,58,237,0.25)',
                        ring: '0 0 0 1px rgba(255,255,255,0.08)'
                    }
                }
            }
        }
        const token = localStorage.getItem('finot_token');
        if (!token) { window.location.href = '/login'; }
        if (!window.tokenValidated) {
            // Force JSON response so auth failures don't redirect to HTML login (which breaks fetch().json()).
            fetch('/api/v1/me', { headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' } }).then(async res => {
                if (res.status === 401) {
                    localStorage.removeItem('finot_token');
                    localStorage.removeItem('finot_user');
                    window.location.href = '/login';
                    return;
                }
                window.tokenValidated = res.ok;
            }).catch(() => {
                localStorage.removeItem('finot_token');
                localStorage.removeItem('finot_user');
                window.location.href = '/login';
            });
        }
        const originalFetch = window.fetch;
        window.fetch = function(url, options = {}) {
            const auth = localStorage.getItem('finot_token');
            const headers = new Headers(options.headers || {});
            if (auth) headers.set('Authorization', `Bearer ${auth}`);
            const u = String(url || '');
            if ((u.startsWith('/api/') || u.includes('/api/')) && !headers.has('Accept')) {
                headers.set('Accept', 'application/json');
            }
            options.headers = headers;
            return originalFetch(url, options);
        };
    </script>
    <style>
        :root {
            --grid: radial-gradient(circle at 20% 20%, rgba(34,211,238,0.15), transparent 25%),
                     radial-gradient(circle at 80% 0%, rgba(124,58,237,0.2), transparent 28%),
                     radial-gradient(circle at 40% 80%, rgba(52,211,153,0.18), transparent 30%);
        }
        body { font-family: 'Space Grotesk', 'Inter', system-ui, -apple-system, sans-serif; background: #070912; color: #e2e8f0; }
        .holo-bg {
            position: fixed; inset: 0;
            background: linear-gradient(120deg, rgba(124,58,237,0.12), rgba(34,211,238,0.12)),
                        linear-gradient(200deg, rgba(15,23,42,0.9), rgba(10,10,20,0.95)),
                        var(--grid);
            background-blend-mode: screen, normal, normal;
            pointer-events: none;
            filter: drop-shadow(0 0 40px rgba(124,58,237,0.18));
        }
        .glass { background: linear-gradient(135deg, rgba(255,255,255,0.06), rgba(255,255,255,0.02)); border: 1px solid rgba(255,255,255,0.06); backdrop-filter: blur(14px); }
        .neon-pill { background: linear-gradient(90deg, rgba(124,58,237,0.9), rgba(34,211,238,0.9)); color: #0b1021; box-shadow: 0 8px 30px rgba(34,211,238,0.28); }
        .card-accent { position: relative; overflow: hidden; }
        .card-accent::after { content: ''; position: absolute; inset: 0; background: linear-gradient(120deg, rgba(124,58,237,0.2), rgba(34,211,238,0)); opacity: 0.7; pointer-events: none; }
        /* Native select dropdown styling for dark theme (option list). */
        select { color-scheme: dark; }
        select option, select optgroup { background-color: #0b1224; color: #e2e8f0; }
        input[type="date"] { color-scheme: dark; }
        /* Light theme overrides */
        .theme-light body { background: #f7f9fb; color: #0f172a; }
        .theme-light .holo-bg { background: radial-gradient(circle at 20% 20%, rgba(34,211,238,0.08), transparent 30%), radial-gradient(circle at 70% 0%, rgba(124,58,237,0.1), transparent 35%), linear-gradient(180deg, #ffffff, #eef2f7); filter: none; }
        .theme-light .glass { background: linear-gradient(135deg, rgba(255,255,255,0.78), rgba(255,255,255,0.92)); border-color: rgba(15,23,42,0.06); color: #0f172a; }
        .theme-light .shadow-glow { box-shadow: 0 10px 50px rgba(15,23,42,0.08); }
        .theme-light .shadow-ring { box-shadow: 0 0 0 1px rgba(15,23,42,0.08); }
        .theme-light .text-white { color: #0f172a !important; }
        .theme-light .text-slate-200 { color: #334155 !important; }
        .theme-light .text-slate-300 { color: #334155 !important; }
        .theme-light .text-slate-400 { color: #475569 !important; }
        .theme-light .bg-white\/10 { background-color: rgba(15,23,42,0.08) !important; }
        .theme-light .bg-white\/5 { background-color: rgba(15,23,42,0.04) !important; }
        .theme-light .neon-pill { color: #0f172a; }
        .theme-light select { color-scheme: light; }
        .theme-light select option, .theme-light select optgroup { background-color: #ffffff; color: #0f172a; }
        .theme-light input[type="date"] { color-scheme: light; }
        /* Sidebar needs to be readable (less transparent than generic glass). */
        .sidebar {
            background-color: rgba(9, 12, 24, 0.92);
            backdrop-filter: blur(18px);
        }
        .sidebar.glass {
            background: linear-gradient(135deg, rgba(255,255,255,0.10), rgba(255,255,255,0.04));
            border-color: rgba(255,255,255,0.08);
        }
        .theme-light .sidebar {
            background-color: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(12px);
        }
        .theme-light .sidebar.glass {
            background: linear-gradient(135deg, rgba(255,255,255,0.92), rgba(255,255,255,0.98));
            border-color: rgba(15,23,42,0.08);
        }
        /* Sidebar collapse */
        .sidebar-collapsed #sidebar { width: 5rem; }
        .sidebar-collapsed #sidebar .nav-label,
        .sidebar-collapsed #sidebar .brand-text,
        .sidebar-collapsed #sidebar .status-copy { display: none; }
        .sidebar-collapsed #collapse-toggle i { transform: rotate(180deg); }
    </style>
    @stack('head')
</head>
<body class="min-h-screen">
    <div class="holo-bg"></div>
    <div class="relative z-10 flex h-screen" id="layout-shell">
        <div id="sidebar-backdrop" class="fixed inset-0 bg-black/40 hidden lg:hidden z-[60]"></div>
        <aside id="sidebar" class="sidebar glass shadow-glow border-r border-white/5 flex flex-col fixed lg:relative h-full w-72 lg:w-72 -translate-x-full lg:translate-x-0 transition-all duration-300 ease-in-out z-[70]">
            <div class="p-6 flex items-center gap-3">
                <div class="h-12 w-12 rounded-2xl neon-pill flex items-center justify-center text-xl">F</div>
                <div class="brand-text">
                    <!-- <p class="text-xs uppercase tracking-[0.3em] text-slate-400">Finot</p> -->
                    <p class="text-xl text-white font-medium">Finot</p>
                </div>
                <button id="sidebar-close" type="button" class="ml-auto h-10 w-10 rounded-lg glass flex items-center justify-center text-slate-300 hover:text-white transition shadow-ring lg:hidden" aria-label="Close menu">
                    <i class="fas fa-xmark"></i>
                </button>
                <button id="collapse-toggle" type="button" class="ml-auto h-10 w-10 rounded-lg glass hidden lg:flex items-center justify-center text-slate-300 hover:text-white transition shadow-ring" aria-label="Collapse sidebar">
                    <i class="fas fa-angles-left"></i>
                </button>
            </div>
            <nav class="px-3 space-y-2 text-slate-300">
                <a class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/5 transition {{ request()->routeIs('admin.dashboard') ? 'bg-white/10 text-white shadow-ring' : '' }}" href="{{ route('admin.dashboard') }}">
                    <i class="fas fa-microchip"></i><span class="nav-label">Dashboard</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/5 transition {{ request()->routeIs('admin.classes') ? 'bg-white/10 text-white shadow-ring' : '' }}" href="{{ route('admin.classes') }}">
                    <i class="fas fa-chalkboard-teacher"></i><span class="nav-label">Classes</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/5 transition {{ request()->routeIs('admin.teacher_accounts') ? 'bg-white/10 text-white shadow-ring' : '' }}" href="{{ route('admin.teacher_accounts') }}">
                    <i class="fas fa-id-badge"></i><span class="nav-label">Teacher Accounts</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/5 transition {{ request()->routeIs('admin.students') ? 'bg-white/10 text-white shadow-ring' : '' }}" href="{{ route('admin.students') }}">
                    <i class="fas fa-user-graduate"></i><span class="nav-label">Students</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/5 transition {{ request()->routeIs('admin.attendance') ? 'bg-white/10 text-white shadow-ring' : '' }}" href="{{ route('admin.attendance') }}">
                    <i class="fas fa-calendar-check"></i><span class="nav-label">Attendance</span>
                </a>
                {{-- Alerts temporarily removed from sidebar --}}
                <a class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/5 transition {{ request()->routeIs('admin.reports') ? 'bg-white/10 text-white shadow-ring' : '' }}" href="{{ route('admin.reports') }}">
                    <i class="fas fa-file-export"></i><span class="nav-label">Reports</span>
                </a>
            </nav>
            <div class="mt-auto p-6">
                <div class="glass rounded-xl px-4 py-3 text-sm text-slate-200 shadow-ring">
                    <div class="flex items-center justify-between">
                        <div class="status-copy">
                            <p class="text-xs uppercase tracking-wide text-slate-400">Status</p>
                            <p class="text-neon font-medium">Systems nominal</p>
                        </div>
                        <span class="h-3 w-3 rounded-full bg-emerald-400 shadow-glow"></span>
                    </div>
                </div>
            </div>
        </aside>

        <main class="flex-1 overflow-y-auto overflow-x-hidden">
            <header class="px-4 md:px-8 py-5 flex items-start sm:items-center justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <p class="text-xs uppercase tracking-[0.35em] text-slate-400 truncate sm:whitespace-normal">@yield('page-label', 'Live analytics')</p>
                    <h2 class="text-2xl md:text-3xl text-white font-medium leading-tight break-words">@yield('page-title', 'Dashboard')</h2>
                    <p class="text-slate-400 text-sm mt-1 truncate sm:whitespace-normal">@yield('page-subtitle', '')</p>
                </div>
                <div class="flex items-center gap-2 md:gap-4 shrink-0">
                    <button id="mobile-menu-btn" type="button" class="h-11 w-11 rounded-xl glass flex items-center justify-center text-slate-200 hover:text-white transition shadow-ring lg:hidden" aria-label="Open menu">
                        <i class="fas fa-bars"></i>
                    </button>
                    <button class="relative h-11 w-11 rounded-xl glass items-center justify-center text-slate-200 hover:text-white transition shadow-ring hidden sm:flex" aria-label="Notifications">
                        <i class="fas fa-bell"></i>
                        <span class="absolute -top-1 -right-1 h-5 w-5 text-xs bg-gradient-to-r from-amber-400 to-pink-500 text-black rounded-full flex items-center justify-center">3</span>
                    </button>
                    <button id="theme-toggle" type="button" class="h-11 w-11 sm:w-auto px-0 sm:px-4 rounded-xl glass text-slate-200 hover:text-white transition shadow-ring flex items-center justify-center gap-2" aria-label="Toggle theme">
                        <i class="fas fa-moon" id="theme-icon"></i>
                        <span class="hidden sm:inline text-sm">Theme</span>
                    </button>
                    <button id="logout-btn" type="button" class="h-11 w-11 sm:w-auto px-0 sm:px-4 rounded-xl neon-pill text-sm font-medium flex items-center justify-center gap-2" aria-label="Logout">
                        <i class="fas fa-right-from-bracket"></i>
                        <span class="hidden sm:inline">Logout</span>
                    </button>
                </div>
            </header>
            <section class="px-4 md:px-8 pb-10 space-y-8">
                @yield('content')
            </section>
        </main>
    </div>
    @stack('scripts')
    <script>
        document.getElementById('logout-btn')?.addEventListener('click', () => {
            localStorage.removeItem('finot_token');
            localStorage.removeItem('finot_user');
            window.location.href = '/login';
        });

        // Theme toggle
        const themeButton = document.getElementById('theme-toggle');
        const themeIcon = document.getElementById('theme-icon');
        const applyTheme = (mode) => {
            const root = document.documentElement;
            root.classList.toggle('theme-light', mode === 'light');
            themeIcon.className = mode === 'light' ? 'fas fa-sun' : 'fas fa-moon';
        };
        const savedTheme = localStorage.getItem('finot_theme') || 'dark';
        applyTheme(savedTheme);
        themeButton?.addEventListener('click', () => {
            const next = document.documentElement.classList.contains('theme-light') ? 'dark' : 'light';
            localStorage.setItem('finot_theme', next);
            applyTheme(next);
        });

        // Sidebar: mobile open/close and desktop collapse
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('sidebar-backdrop');
        const mobileBtn = document.getElementById('mobile-menu-btn');
        const collapseBtn = document.getElementById('collapse-toggle');
        const root = document.documentElement;

        const openSidebarMobile = () => {
            sidebar.classList.remove('-translate-x-full');
            backdrop.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        };
        const closeSidebarMobile = () => {
            sidebar.classList.add('-translate-x-full');
            backdrop.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        };

        mobileBtn?.addEventListener('click', openSidebarMobile);
        backdrop?.addEventListener('click', closeSidebarMobile);
        document.getElementById('sidebar-close')?.addEventListener('click', closeSidebarMobile);

        // Close sidebar when selecting a nav item on mobile.
        document.querySelectorAll('#sidebar a[href]').forEach(a => {
            a.addEventListener('click', () => {
                if (window.innerWidth < 1024) closeSidebarMobile();
            });
        });

        // Escape to close on mobile.
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && window.innerWidth < 1024) closeSidebarMobile();
        });

        collapseBtn?.addEventListener('click', () => {
            const collapsed = root.classList.toggle('sidebar-collapsed');
            localStorage.setItem('finot_sidebar', collapsed ? 'collapsed' : 'expanded');
        });

        // restore collapse state
        const savedSidebar = localStorage.getItem('finot_sidebar');
        if (savedSidebar === 'collapsed') {
            root.classList.add('sidebar-collapsed');
        }
    </script>
</body>
</html>
