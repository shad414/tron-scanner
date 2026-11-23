<!doctype html>
<html lang="ckb" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#070b10">
    <meta name="color-scheme" content="dark">
    <title>@yield('title', 'پشکنەری ئادرەسی TRON')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com/3.4.17"></script>
    <style>
        :root {
            --bg: #070b10; --card: #0f1821; --card2: #142030; --line: #1d2a37; --line2: #2a3a4b;
            --ink: #e8eef5; --muted: #8b9aab; --faint: #5f6d7e;
            --brand: #2dd4bf; --brand-ink: #032420;
            --ok: #34d399; --ok-bg: rgba(52, 211, 153, .12);
            --warn: #fbbf24; --warn-bg: rgba(251, 191, 36, .12);
            --bad: #f87171; --bad-bg: rgba(248, 113, 113, .12);
            --blue: #60a5fa; --gold: #f5c451;
        }
        * { -webkit-tap-highlight-color: transparent; }
        html { -webkit-text-size-adjust: 100%; }
        body {
            font-family: 'Vazirmatn', 'Noto Sans Arabic', system-ui, sans-serif; color: var(--ink); min-height: 100vh;
            background: radial-gradient(800px 420px at 90% -10%, rgba(45, 212, 191, .12), transparent 60%),
                        radial-gradient(600px 380px at -10% 10%, rgba(96, 165, 250, .07), transparent 60%), var(--bg);
            background-attachment: fixed;
        }
        .mono { font-family: ui-monospace, 'SF Mono', Consolas, monospace; direction: ltr; unicode-bidi: embed; }
        .muted { color: var(--muted); } .faint { color: var(--faint); }
        .c-ok { color: var(--ok); } .c-warn { color: var(--warn); } .c-bad { color: var(--bad); } .c-blue { color: var(--blue); } .c-gold { color: var(--gold); }

        /* top bar */
        .topbar { position: sticky; top: 0; z-index: 30; backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
            background: rgba(7, 11, 16, .78); border-bottom: 1px solid var(--line); padding-top: env(safe-area-inset-top); }
        .logo-mark { width: 36px; height: 36px; border-radius: 11px; display: grid; place-items: center; font-weight: 800; font-size: 19px;
            color: var(--brand-ink); background: linear-gradient(135deg, #5eead4, #14b8a6); box-shadow: 0 6px 18px rgba(45, 212, 191, .3); }
        .nav-link { color: var(--muted); padding: 7px 12px; border-radius: 10px; font-size: 14px; transition: .15s; }
        .nav-link:hover { color: var(--ink); } .nav-link.on { color: var(--brand); background: var(--card2); }

        /* bottom tab bar (phones) */
        .tabbar { position: fixed; inset-inline: 0; bottom: 0; z-index: 30; display: flex; background: rgba(10, 16, 23, .92);
            backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border-top: 1px solid var(--line);
            padding-bottom: env(safe-area-inset-bottom); }
        .tabbar a { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 3px; padding: 9px 0 8px; font-size: 11.5px; color: var(--faint); }
        .tabbar a.on { color: var(--brand); }
        .tabbar svg { width: 23px; height: 23px; }
        .has-tabbar main { padding-bottom: calc(88px + env(safe-area-inset-bottom)); }

        /* surfaces */
        .card { background: linear-gradient(180deg, var(--card2), var(--card)); border: 1px solid var(--line); border-radius: 20px; }
        .sec { margin-bottom: 14px; }
        .sec > summary { list-style: none; cursor: pointer; display: flex; align-items: center; gap: 10px; padding: 16px 18px; font-weight: 700; font-size: 16px; }
        .sec > summary::-webkit-details-marker { display: none; }
        .sec > summary .chev { margin-inline-start: auto; color: var(--faint); transition: transform .2s; }
        .sec[open] > summary .chev { transform: rotate(180deg); }
        .sec > .body { padding: 0 18px 18px; }
        .sec-icon { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; background: var(--card); border: 1px solid var(--line); font-size: 17px; }
        .pill { display: inline-flex; align-items: center; gap: 4px; border-radius: 999px; padding: 2px 9px; font-size: 12px; font-weight: 600; white-space: nowrap; }
        .pill-ok { background: var(--ok-bg); color: var(--ok); } .pill-warn { background: var(--warn-bg); color: var(--warn); }
        .pill-bad { background: var(--bad-bg); color: var(--bad); } .pill-gold { background: rgba(245, 196, 81, .13); color: var(--gold); }
        .pill-blue { background: rgba(96, 165, 250, .13); color: var(--blue); } .pill-mute { background: rgba(255, 255, 255, .06); color: var(--muted); }
        .row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 11px 0; border-bottom: 1px solid var(--line); }
        .row:last-child { border-bottom: 0; }

        /* controls */
        .field { width: 100%; background: #0a1118; border: 1px solid var(--line2); border-radius: 14px; color: var(--ink); padding: 12px 14px; font-size: 16px; outline: none; transition: border-color .15s, box-shadow .15s; }
        .field:focus { border-color: var(--brand); box-shadow: 0 0 0 4px rgba(45, 212, 191, .15); }
        .field::placeholder { color: #4d5b6b; font-family: 'Vazirmatn', system-ui, sans-serif; }
        .field.mono:placeholder-shown { direction: rtl; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px; border-radius: 14px; padding: 11px 16px; font-weight: 600; font-size: 14.5px;
            background: var(--card2); border: 1px solid var(--line2); color: var(--ink); transition: .15s; white-space: nowrap; }
        .btn:hover { border-color: var(--muted); } .btn:active { transform: scale(.97); }
        .btn-primary { background: linear-gradient(135deg, #5eead4, #14b8a6); border-color: transparent; color: var(--brand-ink); font-weight: 800; box-shadow: 0 8px 22px rgba(45, 212, 191, .25); }
        .btn-primary:disabled { opacity: .6; }
        .btn-danger { background: var(--bad-bg); border-color: rgba(248, 113, 113, .35); color: var(--bad); }
        .seg { display: flex; background: #0a1118; border: 1px solid var(--line2); border-radius: 12px; padding: 3px; gap: 3px; }
        .seg button { flex: 1; padding: 7px 10px; border-radius: 9px; font-size: 13px; color: var(--muted); white-space: nowrap; }
        .seg button.on { background: var(--card2); color: var(--ink); font-weight: 700; box-shadow: inset 0 0 0 1px var(--line2); }
        /* these also carry .btn/.row (display set), so Tailwind's .hidden alone can lose */
        #pasteBtn.hidden, #clearBtn.hidden, #rf-more.hidden, .rt.hidden { display: none; }
        .hscroll { display: flex; gap: 8px; overflow-x: auto; scrollbar-width: none; margin-inline: -16px; padding-inline: 16px; }
        .hscroll::-webkit-scrollbar { display: none; }

        /* Laravel's default pagination markup is light; tone it down */
        nav[role=navigation] a, nav[role=navigation] span[aria-disabled], nav[role=navigation] span[aria-current] > span {
            background: var(--card) !important; color: var(--ink) !important; border-color: var(--line) !important; }
        nav[role=navigation] span[aria-current] > span { color: var(--brand) !important; }
        nav[role=navigation] p { color: var(--muted) !important; }

        /* toast */
        #toast { position: fixed; left: 50%; bottom: calc(100px + env(safe-area-inset-bottom)); transform: translate(-50%, 20px); opacity: 0; z-index: 60;
            background: #e8eef5; color: #0b1118; font-weight: 700; padding: 10px 18px; border-radius: 999px; font-size: 14px; transition: .25s; pointer-events: none; }
        #toast.show { opacity: 1; transform: translate(-50%, 0); }

        @media print {
            :root { --bg: #fff; --card: #fff; --card2: #fff; --line: #ddd; --line2: #ccc; --ink: #111; --muted: #555; --faint: #777;
                --ok: #15803d; --warn: #b45309; --bad: #c62828; --blue: #1d4ed8; --gold: #a16207; }
            body { background: #fff !important; }
            .no-print, .topbar, .tabbar { display: none !important; }
            .card { box-shadow: none; } .print-break { break-inside: avoid; }
            #recent .rt { display: flex !important; } /* print every transfer, not just the visible page */
            a { color: inherit !important; text-decoration: none !important; }
            .has-tabbar main { padding-bottom: 0; }
        }
    </style>
</head>
@php
    $isAdmin = \App\Http\Middleware\RequireAdmin::isAdmin(request());
    $tabs = [
        ['scan', 'scan', 'پشکنین', '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>'],
        ['history', 'history*', 'مێژوو', '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'],
        ['watches', 'watches', 'چاودێری', '<path d="M6 8a6 6 0 1 1 12 0c0 7 3 8 3 8H3s3-1 3-8"/><path d="M10 20a2 2 0 0 0 4 0"/>'],
    ];
@endphp
<body class="{{ $isAdmin ? 'has-tabbar' : '' }}">
<header class="topbar no-print">
    <div class="max-w-5xl mx-auto px-4 h-[60px] flex items-center gap-3">
        <a href="{{ route('scan') }}" class="flex items-center gap-2.5 ml-auto">
            <span class="logo-mark">₮</span>
            <span class="leading-tight">
                <span class="block font-extrabold text-[16px]">پشکنەری TRON</span>
                <span class="block text-[11px] muted">AML · TRC20 · USDT</span>
            </span>
        </a>
        @if ($isAdmin)
            <nav class="hidden md:flex items-center gap-1">
                @foreach ($tabs as [$route, $match, $label])
                    <a href="{{ route($route) }}" class="nav-link {{ request()->routeIs($match) ? 'on' : '' }}">{{ $label }}</a>
                @endforeach
            </nav>
        @endif
        @if (session('scanner_ok'))
            <form method="post" action="{{ route('logout') }}">@csrf<button class="nav-link">چوونەدەرەوە</button></form>
        @elseif (config('services.tron.admin_password') && ! $isAdmin)
            <a href="{{ route('login', ['admin' => 1]) }}" class="nav-link text-xs">🔑 بەڕێوەبەر</a>
        @endif
    </div>
</header>

<main class="max-w-3xl mx-auto px-4 pt-5 pb-10">
    @if (session('status'))
        <div class="no-print card p-3.5 mb-4 text-sm" style="border-color: rgba(52,211,153,.4); color: var(--ok)">✓ {{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="no-print card p-3.5 mb-4 text-sm" style="border-color: rgba(248,113,113,.4); color: var(--bad)">{{ $errors->first() }}</div>
    @endif
    @yield('content')
</main>

@if ($isAdmin)
    <nav class="tabbar no-print md:hidden">
        @foreach ($tabs as [$route, $match, $label, $icon])
            <a href="{{ route($route) }}" class="{{ request()->routeIs($match) ? 'on' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $icon !!}</svg>
                {{ $label }}
            </a>
        @endforeach
    </nav>
@endif

<div id="toast"></div>
<script>
    // [data-copy] buttons anywhere on the page, with a small confirmation toast
    window.toast = function (msg) {
        const t = document.getElementById('toast');
        t.textContent = msg; t.classList.add('show');
        clearTimeout(t._h); t._h = setTimeout(() => t.classList.remove('show'), 1800);
    };
    document.addEventListener('click', async e => {
        const b = e.target.closest('[data-copy]');
        if (!b) return;
        e.preventDefault();
        try { await navigator.clipboard.writeText(b.dataset.copy); toast('کۆپی کرا ✓'); } catch { toast('کۆپی نەکرا'); }
    });
    // closed sections still belong in the printed report
    window.addEventListener('beforeprint', () => document.querySelectorAll('details').forEach(d => d.open = true));
</script>
@stack('scripts')
</body>
</html>
