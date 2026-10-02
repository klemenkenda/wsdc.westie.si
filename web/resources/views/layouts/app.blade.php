<!DOCTYPE html>
<html lang="sl" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Skupna lestvica') · SLO WSDC Ranking</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,{{ rawurlencode(trim(view('partials.logo')->render())) }}">
    <script>
        // Apply saved/system theme before first paint to avoid a flash
        (function () {
            var t = null;
            try { t = localStorage.getItem('theme'); } catch (e) {}
            if (t !== 'light' && t !== 'dark') {
                t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            document.documentElement.setAttribute('data-bs-theme', t);
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* ── Design tokens ─────────────────────────────────────────────── */
        :root, [data-bs-theme="light"] {
            --brand: #4f46e5;
            --brand-2: #db2777;
            --brand-rgb: 79, 70, 229;
            --surface: #ffffff;
            --surface-2: #f8fafc;
            --page-bg: #f4f5fb;
            --border-soft: #e5e7eb;
            --text-muted: #64748b;
            --shadow-sm: 0 1px 2px rgba(15, 23, 42, .04), 0 1px 3px rgba(15, 23, 42, .06);
            --shadow-md: 0 4px 24px -6px rgba(15, 23, 42, .10);
            --leader: #6366f1;
            --follower: #f43f5e;

            --bs-body-font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            --bs-body-bg: var(--page-bg);
            --bs-body-color: #0f172a;
            --bs-secondary-color: var(--text-muted);
            --bs-border-color: var(--border-soft);
            --bs-primary: var(--brand);
            --bs-primary-rgb: var(--brand-rgb);
            --bs-link-color: var(--brand);
            --bs-link-color-rgb: var(--brand-rgb);
            --bs-link-hover-color: #4338ca;
            --bs-link-hover-color-rgb: 67, 56, 202;
            --bs-border-radius: .6rem;
            --bs-border-radius-lg: .9rem;
        }
        [data-bs-theme="dark"] {
            --brand: #818cf8;
            --brand-2: #f472b6;
            --brand-rgb: 129, 140, 248;
            --surface: #151a2d;
            --surface-2: #1b2137;
            --page-bg: #0b0f1c;
            --border-soft: #262d45;
            --text-muted: #94a3b8;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, .3);
            --shadow-md: 0 8px 30px -8px rgba(0, 0, 0, .5);
            --leader: #818cf8;
            --follower: #fb7185;

            --bs-body-bg: var(--page-bg);
            --bs-body-color: #e2e8f0;
            --bs-secondary-color: var(--text-muted);
            --bs-border-color: var(--border-soft);
            --bs-tertiary-bg: var(--surface-2);
            --bs-primary: var(--brand);
            --bs-primary-rgb: var(--brand-rgb);
            --bs-link-color: var(--brand);
            --bs-link-color-rgb: var(--brand-rgb);
            --bs-link-hover-color: #a5b4fc;
            --bs-link-hover-color-rgb: 165, 180, 252;
        }

        body {
            background: var(--page-bg);
            background-image:
                radial-gradient(60rem 30rem at 100% -10%, rgba(var(--brand-rgb), .10), transparent 60%),
                radial-gradient(40rem 24rem at -10% 0%, rgba(219, 39, 119, .06), transparent 60%);
            background-repeat: no-repeat;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            -webkit-font-smoothing: antialiased;
            font-feature-settings: 'cv11', 'ss01';
        }
        main { flex: 1 0 auto; }
        a { text-decoration: none; }
        .text-muted { color: var(--text-muted) !important; }

        /* ── Navbar ────────────────────────────────────────────────────── */
        .app-nav {
            position: sticky; top: 0; z-index: 1030;
            background: color-mix(in srgb, var(--surface) 78%, transparent);
            backdrop-filter: saturate(180%) blur(14px);
            -webkit-backdrop-filter: saturate(180%) blur(14px);
            border-bottom: 1px solid var(--border-soft);
        }
        .app-nav .navbar-brand {
            display: flex; align-items: center; gap: .6rem;
            font-weight: 800; letter-spacing: -.01em; color: var(--bs-body-color);
        }
        .brand-mark { display: block; width: 2.4rem; height: 2.4rem; flex-shrink: 0; border-radius: .7rem; box-shadow: 0 6px 16px -6px rgba(var(--brand-rgb), .7); transition: transform .2s; }
        .brand-mark svg { display: block; width: 100%; height: 100%; }
        .navbar-brand:hover .brand-mark { transform: rotate(-6deg) scale(1.05); }
        .brand-name { line-height: 1.1; font-size: 1.15rem; }
        .brand-accent { background: linear-gradient(135deg, var(--brand), var(--brand-2)); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .brand-sub { display: block; font-size: .68rem; font-weight: 500; color: var(--text-muted); letter-spacing: .02em; margin-top: .2rem; }
        .app-nav .nav-link {
            font-weight: 500; font-size: .92rem; color: var(--text-muted);
            padding: .45rem .85rem !important; border-radius: 999px;
            transition: background .15s, color .15s;
        }
        .app-nav .nav-link:hover { color: var(--bs-body-color); background: rgba(var(--brand-rgb), .07); }
        .app-nav .nav-link.active { color: var(--brand); background: rgba(var(--brand-rgb), .12); font-weight: 600; }
        .app-nav .navbar-toggler { border: 0; box-shadow: none; }
        .icon-btn {
            width: 2.25rem; height: 2.25rem; border-radius: 999px;
            display: inline-grid; place-items: center;
            border: 1px solid var(--border-soft); background: var(--surface); color: var(--bs-body-color);
        }
        .icon-btn:hover { border-color: rgba(var(--brand-rgb), .5); }
        .btn-update {
            border-radius: 999px; font-weight: 600; font-size: .85rem; padding: .4rem 1rem;
            color: #fff; border: 0;
            background: linear-gradient(135deg, var(--brand), var(--brand-2));
            box-shadow: 0 6px 16px -8px rgba(var(--brand-rgb), .8);
        }
        .btn-update:hover { color: #fff; filter: brightness(1.08); }

        /* ── Page header ───────────────────────────────────────────────── */
        .page-header { margin: 2rem 0 1.5rem; }
        .page-header h1 { font-weight: 800; letter-spacing: -.025em; font-size: clamp(1.6rem, 3vw, 2.1rem); margin-bottom: .35rem; }
        .page-header p { color: var(--text-muted); margin-bottom: 0; max-width: 46rem; }
        .eyebrow { font-size: .72rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--brand); margin-bottom: .4rem; }

        /* ── Cards ─────────────────────────────────────────────────────── */
        .card {
            background: var(--surface);
            border: 1px solid var(--border-soft);
            border-radius: var(--bs-border-radius-lg);
            box-shadow: var(--shadow-sm);
        }
        .card-header {
            background: transparent; border-bottom: 1px solid var(--border-soft);
            padding: .9rem 1.15rem; font-weight: 600;
        }
        .card-header:first-child { border-radius: var(--bs-border-radius-lg) var(--bs-border-radius-lg) 0 0; }
        .list-group-item { background: transparent; border-color: var(--border-soft); }

        /* ── Tables ────────────────────────────────────────────────────── */
        .table-card { overflow: hidden; }
        .table-card .table-responsive { margin: 0; }
        .table {
            --bs-table-bg: transparent;
            --bs-table-hover-bg: rgba(var(--brand-rgb), .045);
            --bs-table-border-color: var(--border-soft);
            margin-bottom: 0;
        }
        .table td, .table th { font-variant-numeric: tabular-nums; }
        .table > thead th,
        .table > thead.table-dark th,
        .table > thead.table-light th {
            background: var(--surface-2) !important;
            color: var(--text-muted) !important;
            font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em;
            border-bottom: 1px solid var(--border-soft);
            padding-top: .75rem; padding-bottom: .75rem;
            white-space: nowrap;
        }
        .table > tbody > tr > td { padding-top: .7rem; padding-bottom: .7rem; border-color: var(--border-soft); }
        .table > tbody > tr:last-child > td { border-bottom: 0; }
        .table > :not(caption) > * > *:first-child { padding-left: 1.1rem; }
        .table > :not(caption) > * > *:last-child { padding-right: 1.1rem; }
        td.col-rank, th.col-rank { width: 3.5rem; text-align: center; }
        td.col-pts, th.col-pts { width: 3.5rem; text-align: right; }
        .pts-zero { color: var(--text-muted); opacity: .45; }
        .dancer-link { font-weight: 600; color: var(--bs-body-color); }
        td > .dancer-link:first-child { white-space: nowrap; }
        .dancer-link:hover { color: var(--brand); }
        .best-event { font-size: .88rem; }
        .best-meta { font-size: .76rem; color: var(--text-muted); }

        .rank-num {
            display: inline-grid; place-items: center;
            width: 2rem; height: 2rem; border-radius: 999px;
            font-size: .85rem; font-weight: 700; color: var(--text-muted);
            background: var(--surface-2);
        }
        .rank-1 { background: linear-gradient(135deg, #fde68a, #f59e0b); color: #78350f; }
        .rank-2 { background: linear-gradient(135deg, #e5e7eb, #9ca3af); color: #1f2937; }
        .rank-3 { background: linear-gradient(135deg, #fed7aa, #c2410c); color: #fff; }

        /* ── Division & role badges ────────────────────────────────────── */
        .division-badge {
            --c: #94a3b8;
            display: inline-block;
            font-size: .68rem; font-weight: 700; letter-spacing: .05em;
            padding: .22em .6em; border-radius: 999px; line-height: 1.3;
            color: color-mix(in srgb, var(--c) 82%, #000);
            background: color-mix(in srgb, var(--c) 14%, transparent);
            border: 1px solid color-mix(in srgb, var(--c) 32%, transparent);
        }
        [data-bs-theme="dark"] .division-badge {
            color: color-mix(in srgb, var(--c) 65%, #fff);
            background: color-mix(in srgb, var(--c) 20%, transparent);
        }
        .div-CHA { --c: #8b5cf6; }
        .div-ALS { --c: #3b82f6; }
        .div-ADV { --c: #10b981; }
        .div-INT { --c: #f59e0b; }
        .div-NOV { --c: #06b6d4; }
        .div-NEW { --c: #94a3b8; }

        .role-badge {
            display: inline-flex; align-items: center; gap: .35em;
            font-size: .75rem; font-weight: 600; padding: .2em .65em; border-radius: 999px;
            color: var(--rc); background: color-mix(in srgb, var(--rc) 12%, transparent);
        }
        .role-badge::before { content: ''; width: .45em; height: .45em; border-radius: 50%; background: var(--rc); }
        .role-leader   { --rc: var(--leader); }
        .role-follower { --rc: var(--follower); }
        .secondary-role { font-size: .72rem; color: var(--text-muted); }

        /* ── Controls ──────────────────────────────────────────────────── */
        .seg {
            display: inline-flex; padding: .2rem; gap: .15rem;
            background: var(--surface); border: 1px solid var(--border-soft); border-radius: 999px;
            box-shadow: var(--shadow-sm);
        }
        .seg .btn {
            border: 0 !important; border-radius: 999px !important;
            font-size: .82rem; font-weight: 500; padding: .35rem .9rem;
            color: var(--text-muted); background: transparent;
        }
        .seg .btn:hover { color: var(--bs-body-color); }
        .seg .btn.active, .seg .btn-check:checked + .btn {
            background: var(--brand); color: #fff; box-shadow: 0 4px 12px -6px rgba(var(--brand-rgb), .9);
        }
        .btn-primary {
            --bs-btn-bg: var(--brand); --bs-btn-border-color: var(--brand);
            --bs-btn-hover-bg: #4338ca; --bs-btn-hover-border-color: #4338ca;
            --bs-btn-active-bg: #3730a3; --bs-btn-active-border-color: #3730a3;
        }
        .form-control, .form-select { border-color: var(--border-soft); background-color: var(--surface); }
        .form-control:focus, .form-select:focus { border-color: var(--brand); box-shadow: 0 0 0 .2rem rgba(var(--brand-rgb), .18); }
        .alert { border-radius: var(--bs-border-radius-lg); }

        /* ── Accordion ─────────────────────────────────────────────────── */
        .accordion {
            --bs-accordion-bg: var(--surface);
            --bs-accordion-border-color: var(--border-soft);
            --bs-accordion-active-bg: rgba(var(--brand-rgb), .06);
            --bs-accordion-active-color: var(--bs-body-color);
            --bs-accordion-btn-focus-box-shadow: none;
            --bs-accordion-border-radius: var(--bs-border-radius-lg);
            --bs-accordion-inner-border-radius: var(--bs-border-radius-lg);
        }
        .accordion-item { box-shadow: var(--shadow-sm); }
        .accordion-button { font-weight: 500; }

        /* ── Breadcrumb ────────────────────────────────────────────────── */
        .breadcrumb { font-size: .85rem; margin: 1.5rem 0 0; }
        .breadcrumb-item a { color: var(--text-muted); }
        .breadcrumb-item a:hover { color: var(--brand); }

        /* ── Footer ────────────────────────────────────────────────────── */
        .app-footer {
            margin-top: 4rem; padding: 1.5rem 0 2rem;
            border-top: 1px solid var(--border-soft);
            font-size: .82rem; color: var(--text-muted);
        }
        .app-footer a { color: var(--text-muted); text-decoration: underline; text-underline-offset: 2px; }
        .app-footer a:hover { color: var(--brand); }
        .live-dot { display: inline-block; width: .5rem; height: .5rem; border-radius: 50%; background: #10b981; margin-right: .4rem; box-shadow: 0 0 0 3px rgba(16, 185, 129, .2); }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg app-nav">
    <div class="container">
        <a class="navbar-brand" href="/">
            <span class="brand-mark">@include('partials.logo')</span>
            <span class="brand-name">SLO WSDC <span class="brand-accent">Ranking</span><span class="brand-sub">Slovenska West Coast Swing lestvica</span></span>
        </a>
        <div class="d-flex align-items-center gap-2 order-lg-last">
            <button class="icon-btn" type="button" id="themeToggle" title="Preklopi temo" aria-label="Preklopi temo">
                <svg id="themeIcon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></svg>
            </button>
            <a class="btn btn-update d-none d-sm-inline-block" href="/admin" title="Posodobi podatke / Upravljanje">&#x21BB; Posodobi</a>
            <button class="navbar-toggler icon-btn" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-label="Meni">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
        </div>
        <div class="collapse navbar-collapse" id="nav">
            @php
                $navItems = [
                    ['/', 'Skupna lestvica', request()->is('/') || request()->is('ranking/absolute')],
                    ['/ranking/leaders/all', 'Leaders', request()->is('ranking/leaders*')],
                    ['/ranking/followers/all', 'Followers', request()->is('ranking/followers*')],
                    ['/history', 'Zgodovina', request()->is('history')],
                    ['/analysis', 'Analiza', request()->is('analysis')],
                ];
            @endphp
            <ul class="navbar-nav mx-lg-auto gap-lg-1 py-2 py-lg-0">
                @foreach($navItems as [$href, $label, $active])
                <li class="nav-item">
                    <a class="nav-link {{ $active ? 'active' : '' }}" href="{{ $href }}" @if($active) aria-current="page" @endif>{{ $label }}</a>
                </li>
                @endforeach
                <li class="nav-item d-sm-none">
                    <a class="nav-link {{ request()->is('admin*') ? 'active' : '' }}" href="/admin">&#x21BB; Posodobi</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main class="container">
    @yield('content')
</main>

<footer class="app-footer">
    <div class="container d-flex flex-column flex-md-row justify-content-between gap-2">
        <span>Podatki iz <a href="https://points.worldsdc.com" target="_blank" rel="noopener">WSDC Points Registry</a>.</span>
        @if(isset($lastUpdated))
            <span><span class="live-dot"></span>Zadnja posodobitev: {{ $lastUpdated }}</span>
        @endif
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    var root = document.documentElement;
    var icon = document.getElementById('themeIcon');
    var sun  = '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>';
    var moon = '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>';
    function render() { icon.innerHTML = root.getAttribute('data-bs-theme') === 'dark' ? sun : moon; }
    document.getElementById('themeToggle').addEventListener('click', function () {
        var next = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-bs-theme', next);
        try { localStorage.setItem('theme', next); } catch (e) {}
        render();
        document.dispatchEvent(new CustomEvent('themechange', { detail: next }));
    });
    render();
})();
</script>

@stack('scripts')
</body>
</html>
