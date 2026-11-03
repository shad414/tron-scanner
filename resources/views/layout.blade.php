<!doctype html>
<html lang="ckb" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'پشکنەری ئادرەسی TRON')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com/3.4.17"></script>
    <style>
        body { font-family: 'Vazirmatn', system-ui, sans-serif; }
        .mono { font-family: ui-monospace, Consolas, monospace; direction: ltr; unicode-bidi: embed; }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
            .shadow-sm { box-shadow: none !important; border: 1px solid #e2e8f0; }
            .print-break { break-inside: avoid; }
            #recent .rt { display: table-row !important; } /* print every transfer, not just the visible page */
            a { color: inherit !important; text-decoration: none !important; }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen">
<nav class="no-print bg-slate-900 text-white">
    <div class="max-w-5xl mx-auto px-4 py-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
        <a href="{{ route('scan') }}" class="font-extrabold text-base">🔍 پشکنەری TRON</a>
        <a href="{{ route('scan') }}" class="hover:text-red-300 {{ request()->routeIs('scan') ? 'text-red-300' : '' }}">پشکنین</a>
        @if (\App\Http\Middleware\RequireAdmin::isAdmin(request()))
            <a href="{{ route('history') }}" class="hover:text-red-300 {{ request()->routeIs('history*') ? 'text-red-300' : '' }}">مێژووی سکان</a>
            <a href="{{ route('watches') }}" class="hover:text-red-300 {{ request()->routeIs('watches') ? 'text-red-300' : '' }}">چاودێری (Telegram)</a>
        @endif
        @if (session('scanner_ok'))
            <form method="post" action="{{ route('logout') }}" class="mr-auto">@csrf<button class="text-slate-400 hover:text-white">چوونەدەرەوە</button></form>
        @elseif (config('services.tron.admin_password'))
            <a href="{{ route('login', ['admin' => 1]) }}" class="mr-auto text-slate-500 hover:text-white text-xs">🔑 بەڕێوەبەر</a>
        @endif
    </div>
</nav>
<div class="max-w-5xl mx-auto px-4 py-8">
    @if (session('status'))
        <div class="no-print rounded-xl bg-emerald-50 border border-emerald-300 text-emerald-800 p-3 mb-5">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="no-print rounded-xl bg-red-50 border border-red-300 text-red-800 p-3 mb-5">{{ $errors->first() }}</div>
    @endif
    @yield('content')
</div>
</body>
</html>
