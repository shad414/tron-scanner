@php
    // Senders on the right (where the money starts, reading RTL), receivers on the left.
    $senders = array_slice($r['senders'], 0, 8);
    $receivers = array_slice($r['receivers'], 0, 8);
    $rowH = 52;
    $rows = max(count($senders), count($receivers), 1);
    $H = $rows * $rowH + 30;
    $W = 960;
    $cx = $W / 2;
    $cy = $H / 2;
    $nodeW = 230;
    $volume = fn ($c, $dir) => $c[$dir.'_usd'] ?: 0;
    $max = max(1, ...array_map(fn ($c) => $volume($c, 'in'), $senders), ...array_map(fn ($c) => $volume($c, 'out'), $receivers));
    $stroke = fn ($v) => 1.5 + 12 * sqrt($v / $max);
    $y = fn ($i, $n) => $cy + ($i - ($n - 1) / 2) * $rowH;
    // counterparties that dirty money passed through (hop 2 of a taint path)
    $via = [];
    foreach (['in', 'out'] as $d) {
        foreach ($r['taint'][$d]['paths'] ?? [] as $p) {
            if (count($p['hops']) > 1) {
                $via[$d === 'in' ? end($p['hops']) : $p['hops'][0]] = true;
            }
        }
    }
    $kind = function ($c) use ($via) {
        if ($c['flags'] ?? $c['blacklisted'] ?? false) return 'bad';
        if (isset($via[$c['address']])) return 'via';
        if ($c['exchange'] ?? null) return 'exchange';
        return 'plain';
    };
    $fill = ['bad' => '#fee2e2', 'via' => '#ffedd5', 'exchange' => '#fef3c7', 'plain' => '#f8fafc'];
    $border = ['bad' => '#dc2626', 'via' => '#ea580c', 'exchange' => '#d97706', 'plain' => '#cbd5e1'];
    $name = fn ($c) => ($c['exchange'] ?? null)
        ? $c['exchange'].(($c['exchange_kind'] ?? null) === 'deposit' ? ' · دیپۆزیت' : '')
        : (($c['label']['name'] ?? null) ?: (($c['flags'] ?? null) ? '⛔ پیس' : (isset($via[$c['address']]) ? '⚠️ ناوەند' : '')));
@endphp
@if ($senders || $receivers)
<div class="bg-white rounded-2xl shadow-sm p-5 mb-5 print-break">
    <h2 class="font-bold text-lg mb-1">🕸️ نەخشەی ڕۆیشتنی پارە</h2>
    <div class="flex flex-wrap gap-3 text-xs text-slate-500 mb-3">
        <span><span class="inline-block w-3 h-3 rounded-sm align-middle" style="background:#fef3c7;border:1px solid #d97706"></span> ئێکسچەینج</span>
        <span><span class="inline-block w-3 h-3 rounded-sm align-middle" style="background:#fee2e2;border:1px solid #dc2626"></span> پیس / بلاک‌لیست</span>
        <span><span class="inline-block w-3 h-3 rounded-sm align-middle" style="background:#ffedd5;border:1px solid #ea580c"></span> پارەی پیسی پێدا تێپەڕیوە</span>
        <span>ئەستووری هێڵ = بڕی USDT</span>
    </div>
    <div class="overflow-x-auto">
    <svg viewBox="0 0 {{ $W }} {{ $H }}" class="w-full min-w-[720px]" direction="ltr" font-family="Vazirmatn, sans-serif">
        {{-- edges --}}
        @foreach ($senders as $i => $c)
            @php $sy = $y($i, count($senders)); $x1 = $W - $nodeW - 10; $k = $kind($c); @endphp
            <path d="M {{ $x1 }} {{ $sy }} C {{ $x1 - 120 }} {{ $sy }}, {{ $cx + 200 }} {{ $cy }}, {{ $cx + 95 }} {{ $cy }}"
                  fill="none" stroke="{{ $k === 'bad' ? '#dc2626' : '#10b981' }}" stroke-opacity="{{ $k === 'bad' ? .9 : .55 }}"
                  stroke-width="{{ $stroke($volume($c, 'in')) }}" @if ($k === 'bad') stroke-dasharray="6 4" @endif />
        @endforeach
        @foreach ($receivers as $i => $c)
            @php $ry = $y($i, count($receivers)); $x2 = $nodeW + 10; $k = $kind($c); @endphp
            <path d="M {{ $cx - 95 }} {{ $cy }} C {{ $cx - 200 }} {{ $cy }}, {{ $x2 + 120 }} {{ $ry }}, {{ $x2 }} {{ $ry }}"
                  fill="none" stroke="{{ $k === 'bad' ? '#dc2626' : '#f43f5e' }}" stroke-opacity="{{ $k === 'bad' ? .9 : .5 }}"
                  stroke-width="{{ $stroke($volume($c, 'out')) }}" @if ($k === 'bad') stroke-dasharray="6 4" @endif />
        @endforeach

        {{-- center: the scanned address --}}
        <rect x="{{ $cx - 95 }}" y="{{ $cy - 30 }}" width="190" height="60" rx="12" fill="#0f172a" />
        <text x="{{ $cx }}" y="{{ $cy - 6 }}" text-anchor="middle" fill="#fff" font-size="13" font-family="Consolas, monospace">{{ $short($r['address']) }}</text>
        <text x="{{ $cx }}" y="{{ $cy + 16 }}" text-anchor="middle" fill="#94a3b8" font-size="11">{{ $r['exchange'] ?? ($r['label']['name'] ?? 'ئەم ئادرەسە') }}</text>

        {{-- nodes --}}
        @foreach ([[$senders, 'in', $W - $nodeW - 10, '#047857'], [$receivers, 'out', 10, '#be123c']] as [$list, $dir, $x, $color])
            @foreach ($list as $i => $c)
                @php $ny = $y($i, count($list)); $k = $kind($c); $label = $name($c); @endphp
                <a href="{{ route('scan', ['address' => $c['address']]) }}">
                    <title>{{ $c['address'] }}</title>
                    <rect x="{{ $x }}" y="{{ $ny - 21 }}" width="{{ $nodeW }}" height="42" rx="8" fill="{{ $fill[$k] }}" stroke="{{ $border[$k] }}" />
                    <text x="{{ $x + 10 }}" y="{{ $ny - 4 }}" font-size="12" font-family="Consolas, monospace" fill="#1d4ed8">{{ $short($c['address']) }}</text>
                    <text x="{{ $x + $nodeW - 10 }}" y="{{ $ny - 4 }}" font-size="11" text-anchor="end" fill="#92400e" font-weight="600">{{ \Illuminate\Support\Str::limit($label, 18) }}</text>
                    <text x="{{ $x + 10 }}" y="{{ $ny + 13 }}" font-size="11" fill="{{ $color }}" font-family="Consolas, monospace">{{ $volume($c, $dir) ? $fmt(round($volume($c, $dir))).' USDT' : $c[$dir.'_count'].' tx' }}</text>
                </a>
            @endforeach
        @endforeach
    </svg>
    </div>
</div>
@endif
