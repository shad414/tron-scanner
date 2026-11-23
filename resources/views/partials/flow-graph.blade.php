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
    $max = max([1, ...array_map(fn ($c) => $volume($c, 'in'), $senders), ...array_map(fn ($c) => $volume($c, 'out'), $receivers)]);
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
    $fill = ['bad' => '#3a1214', 'via' => '#3a2010', 'exchange' => '#33290c', 'plain' => '#111b26'];
    $border = ['bad' => '#f87171', 'via' => '#fb923c', 'exchange' => '#f5c451', 'plain' => '#2a3a4b'];
    $name = fn ($c) => ($c['exchange'] ?? null)
        ? $c['exchange'].(($c['exchange_kind'] ?? null) === 'deposit' ? ' · دیپۆزیت' : '')
        : (($c['label']['name'] ?? null) ?: (($c['flags'] ?? null) ? '⛔ پیس' : (isset($via[$c['address']]) ? '⚠️ ناوەند' : '')));
@endphp
@if ($senders || $receivers)
<details class="card sec print-break" open>
    <summary><span class="sec-icon">🕸️</span>نەخشەی ڕۆیشتنی پارە<span class="chev">⌄</span></summary>
    <div class="body">
    <div class="flex flex-wrap gap-x-3 gap-y-1 text-[11.5px] muted mb-4">
        <span><span class="inline-block w-2.5 h-2.5 rounded-sm align-middle" style="background:{{ $border['exchange'] }}"></span> ئێکسچەینج</span>
        <span><span class="inline-block w-2.5 h-2.5 rounded-sm align-middle" style="background:{{ $border['bad'] }}"></span> پیس / بلاک‌لیست</span>
        <span><span class="inline-block w-2.5 h-2.5 rounded-sm align-middle" style="background:{{ $border['via'] }}"></span> پارەی پیسی پێدا تێپەڕیوە</span>
    </div>

    {{-- phones: ranked bars, widest = most USDT --}}
    <div class="md:hidden space-y-5">
        @foreach ([[$senders, 'in', '⬇️ پارە لێرەوە هات', 'var(--ok)'], [$receivers, 'out', '⬆️ پارە بۆ ئێرە چوو', 'var(--bad)']] as [$list, $dir, $title, $color])
            @if ($list)
                <div>
                    <div class="text-[13px] font-bold mb-2" style="color: {{ $color }}">{{ $title }}</div>
                    <div class="space-y-1.5">
                        @foreach ($list as $c)
                            @php $k = $kind($c); $v = $volume($c, $dir); $label = $name($c); @endphp
                            <a href="{{ route('scan', ['address' => $c['address']]) }}" class="block relative rounded-xl overflow-hidden px-3 py-2"
                               style="background: #0a1118; border: 1px solid {{ $k === 'plain' ? 'var(--line)' : $border[$k] }}">
                                <span class="absolute inset-y-0 right-0" style="width: {{ max(4, round(100 * sqrt($v / $max))) }}%; background: {{ $k === 'plain' ? $color : $border[$k] }}; opacity: .16"></span>
                                <span class="relative flex items-center justify-between gap-2">
                                    <span class="min-w-0">
                                        <span class="mono text-[12.5px] c-blue">{{ $short($c['address']) }}</span>
                                        @if ($label)<span class="text-[11.5px] font-semibold mr-1" style="color: {{ $k === 'plain' ? 'var(--gold)' : $border[$k] }}">{{ \Illuminate\Support\Str::limit($label, 22) }}</span>@endif
                                    </span>
                                    <span class="mono text-[12.5px] font-bold shrink-0" style="color: {{ $color }}">{{ $v ? $fmt(round($v)).' $' : $c[$dir.'_count'].' tx' }}</span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </div>

    {{-- wider screens: the full diagram --}}
    <div class="hidden md:block">
    <svg viewBox="0 0 {{ $W }} {{ $H }}" class="w-full" direction="ltr" font-family="Vazirmatn, sans-serif">
        {{-- edges --}}
        @foreach ($senders as $i => $c)
            @php $sy = $y($i, count($senders)); $x1 = $W - $nodeW - 10; $k = $kind($c); @endphp
            <path d="M {{ $x1 }} {{ $sy }} C {{ $x1 - 120 }} {{ $sy }}, {{ $cx + 200 }} {{ $cy }}, {{ $cx + 95 }} {{ $cy }}"
                  fill="none" stroke="{{ $k === 'bad' ? '#f87171' : '#34d399' }}" stroke-opacity="{{ $k === 'bad' ? .9 : .55 }}"
                  stroke-width="{{ $stroke($volume($c, 'in')) }}" @if ($k === 'bad') stroke-dasharray="6 4" @endif />
        @endforeach
        @foreach ($receivers as $i => $c)
            @php $ry = $y($i, count($receivers)); $x2 = $nodeW + 10; $k = $kind($c); @endphp
            <path d="M {{ $cx - 95 }} {{ $cy }} C {{ $cx - 200 }} {{ $cy }}, {{ $x2 + 120 }} {{ $ry }}, {{ $x2 }} {{ $ry }}"
                  fill="none" stroke="{{ $k === 'bad' ? '#f87171' : '#fb7185' }}" stroke-opacity="{{ $k === 'bad' ? .9 : .5 }}"
                  stroke-width="{{ $stroke($volume($c, 'out')) }}" @if ($k === 'bad') stroke-dasharray="6 4" @endif />
        @endforeach

        {{-- center: the scanned address --}}
        <rect x="{{ $cx - 95 }}" y="{{ $cy - 30 }}" width="190" height="60" rx="12" fill="#14b8a6" />
        <text x="{{ $cx }}" y="{{ $cy - 6 }}" text-anchor="middle" fill="#032420" font-weight="700" font-size="13" font-family="Consolas, monospace">{{ $short($r['address']) }}</text>
        <text x="{{ $cx }}" y="{{ $cy + 16 }}" text-anchor="middle" fill="#064e46" font-size="11">{{ $r['exchange'] ?? ($r['label']['name'] ?? 'ئەم ئادرەسە') }}</text>

        {{-- nodes --}}
        @foreach ([[$senders, 'in', $W - $nodeW - 10, '#34d399'], [$receivers, 'out', 10, '#f87171']] as [$list, $dir, $x, $color])
            @foreach ($list as $i => $c)
                @php $ny = $y($i, count($list)); $k = $kind($c); $label = $name($c); @endphp
                <a href="{{ route('scan', ['address' => $c['address']]) }}">
                    <title>{{ $c['address'] }}</title>
                    <rect x="{{ $x }}" y="{{ $ny - 21 }}" width="{{ $nodeW }}" height="42" rx="8" fill="{{ $fill[$k] }}" stroke="{{ $border[$k] }}" />
                    <text x="{{ $x + 10 }}" y="{{ $ny - 4 }}" font-size="12" font-family="Consolas, monospace" fill="#60a5fa">{{ $short($c['address']) }}</text>
                    <text x="{{ $x + $nodeW - 10 }}" y="{{ $ny - 4 }}" font-size="11" text-anchor="end" fill="#f5c451" font-weight="600">{{ \Illuminate\Support\Str::limit($label, 18) }}</text>
                    <text x="{{ $x + 10 }}" y="{{ $ny + 13 }}" font-size="11" fill="{{ $color }}" font-family="Consolas, monospace">{{ $volume($c, $dir) ? $fmt(round($volume($c, $dir))).' USDT' : $c[$dir.'_count'].' tx' }}</text>
                </a>
            @endforeach
        @endforeach
    </svg>
    </div>
    </div>
</details>
@endif
