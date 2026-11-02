@extends('layout')

@section('content')
@php
    $fmt = fn ($n) => rtrim(rtrim(number_format($n, 6, '.', ','), '0'), '.') ?: '0';
    $short = fn ($a) => substr($a, 0, 6).'…'.substr($a, -5);
    $amounts = fn ($m) => collect($m)->map(fn ($v, $k) => $fmt($v).' '.$k)->implode(' · ');
    $riskStyle = ['bg-emerald-50 border-emerald-300 text-emerald-800', 'bg-amber-50 border-amber-300 text-amber-800', 'bg-red-50 border-red-300 text-red-800'];
    $riskIcon = ['✅', '⚠️', '⛔'];
@endphp
    <header class="mb-6 text-center">
        <h1 class="text-3xl font-extrabold text-slate-900">پشکنەری ئادرەسی TRON (TRC20)</h1>
        <p class="text-slate-500 mt-1">باڵانس، سەرچاوە و مەبەستی پارە، بلاک‌لیست و ئاستی مەترسی</p>
    </header>

    <form method="get" action="{{ route('scan') }}" class="no-print flex flex-col sm:flex-row gap-2 mb-6"
          onsubmit="document.getElementById('loading').classList.remove('hidden'); this.querySelector('button').disabled = true">
        <input name="address" value="{{ $address }}" required placeholder="T…"
               class="mono flex-1 rounded-xl border border-slate-300 px-4 py-3 text-lg focus:outline-none focus:ring-2 focus:ring-red-500">
        <button class="rounded-xl bg-red-600 hover:bg-red-700 disabled:opacity-60 text-white font-semibold px-8 py-3">پشکنین</button>
    </form>

    <div id="loading" class="hidden rounded-xl bg-white shadow-sm p-5 mb-6 text-center">
        <div class="inline-block w-8 h-8 border-4 border-red-200 border-t-red-600 rounded-full animate-spin"></div>
        <p class="mt-2 font-semibold">سکان دەکرێت… (نزیکەی ١٠ چرکە)</p>
        <p class="text-sm text-slate-500">مامەڵەکان، ئێکسچەینجەکان و بلاک‌لیست دەپشکنرێن</p>
    </div>

    @if ($error)
        <div class="rounded-xl bg-red-50 border border-red-300 text-red-800 p-4">{{ $error }}</div>
    @endif

    @if ($result)
        @php $r = $result; $risk = $r['risk']; @endphp

        {{-- Risk verdict --}}
        <div class="rounded-2xl border-2 p-5 mb-5 {{ $riskStyle[$risk['level']] }}">
            <div class="text-2xl font-extrabold">{{ $riskIcon[$risk['level']] }} {{ $risk['text'] }}</div>
            @if ($risk['reasons'])
                <ul class="list-disc pr-6 mt-2 space-y-1">
                    @foreach ($risk['reasons'] as $reason) <li>{{ $reason }}</li> @endforeach
                </ul>
            @endif
            @if ($risk['notes'])
                <div class="mt-3 rounded-lg bg-white/70 p-3 text-slate-700 text-sm">
                    <div class="font-semibold mb-1">ℹ️ تێبینی</div>
                    <ul class="list-disc pr-5 space-y-1">
                        @foreach ($risk['notes'] as $note) <li>{{ $note }}</li> @endforeach
                    </ul>
                </div>
            @endif
            <p class="text-xs mt-3 opacity-75">ئەم هەڵسەنگاندنە لەسەر بنەمای داتای گشتی بلۆکچەینە و گەرەنتی تەواو نییە.</p>
        </div>

        {{-- Toolbar: report + watch --}}
        <div class="no-print flex flex-wrap items-center gap-2 mb-5">
            <button type="button" onclick="window.print()" class="rounded-xl bg-slate-800 hover:bg-slate-900 text-white px-4 py-2 text-sm font-semibold">🖨️ ڕاپۆرت / PDF</button>
            @if ($scan)
                <a href="{{ route('history.show', $scan) }}" class="rounded-xl bg-white hover:bg-slate-50 border px-4 py-2 text-sm">🔗 بەستەری ئەم ڕاپۆرتە (#{{ $scan->id }})</a>
            @endif
            <details class="relative">
                <summary class="list-none cursor-pointer rounded-xl bg-white hover:bg-slate-50 border px-4 py-2 text-sm">
                    🔔 چاودێری بە Telegram @if ($watches->count()) <span class="text-emerald-600">({{ $watches->count() }} چالاکە)</span> @endif
                </summary>
                <form method="post" action="{{ route('watches.store') }}" class="absolute z-10 mt-2 w-80 bg-white rounded-xl shadow-lg border p-4 space-y-2">
                    @csrf
                    <input type="hidden" name="address" value="{{ $r['address'] }}">
                    <label class="block text-sm">Telegram Chat ID
                        <input name="chat_id" required inputmode="numeric" placeholder="123456789" class="mono w-full mt-1 rounded-lg border px-3 py-2">
                    </label>
                    <label class="block text-sm">ناو (ئارەزوومەندانە)
                        <input name="label" maxlength="100" placeholder="والێتی کڕیار" class="w-full mt-1 rounded-lg border px-3 py-2">
                    </label>
                    <button class="w-full rounded-lg bg-red-600 hover:bg-red-700 text-white py-2 font-semibold">چاودێری بکە</button>
                    <p class="text-xs text-slate-500">Chat ID ت لە <span class="mono">@userinfobot</span> وەربگرە. <a href="{{ route('watches') }}" class="text-blue-700 underline">ڕێکخستن</a></p>
                </form>
            </details>
            @if ($scan && $scan->created_at->lt(now()->subMinutes(10)))
                <span class="text-sm text-amber-700">ئەمە ڕاپۆرتێکی هەڵگیراوە لە {{ $scan->created_at->format('Y-m-d H:i') }} — <a class="underline" href="{{ route('scan', ['address' => $r['address']]) }}">سکانی نوێ</a></span>
            @endif
        </div>

        {{-- Source of funds (taint) --}}
        @isset($r['taint'])
            @php $taintColor = fn ($p) => $p >= 10 ? 'bg-red-600' : ($p > 0 ? 'bg-amber-500' : 'bg-emerald-500'); @endphp
            <div class="bg-white rounded-2xl shadow-sm p-5 mb-5 print-break">
                <h2 class="font-bold text-lg mb-1">🧪 سەرچاوەی پارە (AML — تا ٢ هەنگاو)</h2>
                <p class="text-xs text-slate-500 mb-4">بەراورد کراوە لەگەڵ {{ number_format(\App\Models\FlaggedAddress::count()) }} ئادرەسی بلاک‌لیستی Tether، سزاکانی OFAC و لیستی خۆت. ئێکسچەینجەکان زنجیرەکە دەبڕن.</p>
                <div class="grid md:grid-cols-2 gap-5">
                    @foreach (['in' => ['⬇️ پارە هاتووەکان', 'لە سەرچاوەی پیسەوە'], 'out' => ['⬆️ پارە ڕۆیشتووەکان', 'گەیشتووە بە ئادرەسی پیس']] as $dir => [$title, $sub])
                        @php $t = $r['taint'][$dir]; @endphp
                        <div>
                            <div class="flex justify-between items-baseline">
                                <span class="font-semibold">{{ $title }}</span>
                                <span class="text-2xl font-extrabold {{ $t['percent'] >= 10 ? 'text-red-600' : ($t['percent'] > 0 ? 'text-amber-600' : 'text-emerald-600') }}">{{ $t['percent'] }}٪</span>
                            </div>
                            <div class="h-3 rounded-full bg-slate-100 overflow-hidden my-1">
                                <div class="h-full {{ $taintColor($t['percent']) }}" style="width: {{ max($t['percent'], $t['percent'] > 0 ? 2 : 0) }}%"></div>
                            </div>
                            <div class="text-xs text-slate-500">{{ $sub }} · {{ $t['coverage'] }}٪ ی بڕەکە شیکرایەوە</div>
                            @foreach ($t['paths'] as $p)
                                <div class="mt-2 text-sm rounded-lg bg-red-50 border border-red-100 p-2">
                                    <div class="mono text-xs break-all">
                                        @php $chain = $dir === 'in' ? [...$p['hops'], $r['address']] : [$r['address'], ...$p['hops']]; @endphp
                                        @foreach ($chain as $i => $hop)
                                            @if ($i) <span class="text-slate-400">→</span> @endif
                                            <a href="{{ route('scan', ['address' => $hop]) }}" class="{{ $hop === $r['address'] ? 'font-bold' : 'text-blue-700 hover:underline' }}" title="{{ $hop }}">{{ $short($hop) }}</a>
                                        @endforeach
                                    </div>
                                    <div class="flex justify-between mt-1">
                                        <span class="text-red-700 text-xs">{{ collect($p['sources'])->map(fn ($s) => \App\Models\FlaggedAddress::SOURCES[$s] ?? $s)->implode('، ') }}</span>
                                        <span class="mono font-semibold">{{ $fmt(round($p['amount'], 2)) }} USDT</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        @endisset

        {{-- Flow graph --}}
        @include('partials.flow-graph', ['r' => $r, 'short' => $short, 'fmt' => $fmt])

        {{-- Summary --}}
        <div class="grid md:grid-cols-2 gap-4 mb-5">
            <div class="bg-white rounded-2xl shadow-sm p-5">
                <h2 class="font-bold text-lg mb-3">زانیاری گشتی</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-2"><dt class="text-slate-500">ئادرەس</dt><dd class="mono break-all text-left">{{ $r['address'] }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">خاوەن / جۆر</dt>
                        <dd class="font-semibold">
                            @if ($r['exchange'])
                                🏦 {{ $r['exchange'] }} — {{ $r['exchange_kind'] === 'deposit' ? 'ئادرەسی دیپۆزیت (هی بەکارهێنەرێکی ئێکسچەینجە)' : ($r['label']['name'] ?? 'والێتی ئێکسچەینج') }}
                            @elseif ($r['label']['name'] ?? null)
                                🏦 {{ $r['label']['name'] }}
                            @elseif ($r['tronscan_enabled'])
                                والێتی تایبەت (ناسناوی گشتی نییە)
                            @else
                                <span class="text-slate-400">نەزانراو — TRONSCAN_API_KEY دابنێ</span>
                            @endif
                        </dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">بلاک‌لیستی USDT (Tether)</dt>
                        <dd class="font-semibold">
                            @if ($r['usdt_blacklisted'] === null) <span class="text-slate-400">نەپشکنرا</span>
                            @elseif ($r['usdt_blacklisted']) <span class="text-red-600">بەڵێ ⛔</span>
                            @else <span class="text-emerald-600">نەخێر ✅</span> @endif
                        </dd></div>
                    @if ($r['security'])
                        <div class="flex justify-between"><dt class="text-slate-500">لیستی ڕەشی Tronscan</dt>
                            <dd class="font-semibold {{ $r['security']['blacklist'] ? 'text-red-600' : 'text-emerald-600' }}">{{ $r['security']['blacklist'] ? 'بەڵێ ⛔' : 'نەخێر ✅' }}</dd></div>
                    @endif
                    <div class="flex justify-between"><dt class="text-slate-500">دروستکراوە لە</dt><dd>{{ $r['created_at'] ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">دوایین چالاکی</dt><dd>{{ $r['last_activity'] ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">مامەڵە شیکراوەکان</dt><dd>{{ $r['analyzed']['trc20'] }} TRC20 · {{ $r['analyzed']['trx'] }} TRX</dd></div>
                </dl>
            </div>

            <div class="bg-white rounded-2xl shadow-sm p-5">
                <h2 class="font-bold text-lg mb-3">باڵانس (چەندی لەسەرە)</h2>
                <div class="space-y-2">
                    @foreach ($r['balance']['tokens'] as $t)
                        <div class="flex justify-between items-baseline">
                            <span class="font-semibold">{{ $t['token'] }}</span>
                            <span class="mono text-xl font-bold">{{ $fmt($t['amount']) }}</span>
                        </div>
                    @endforeach
                    <div class="flex justify-between items-baseline">
                        <span class="font-semibold">TRX</span>
                        <span class="mono text-xl font-bold">{{ $fmt($r['balance']['trx']) }}</span>
                    </div>
                    @if ($r['balance']['trx_staked'] > 0)
                        <div class="flex justify-between text-sm text-slate-500"><span>TRX ی ستەیک کراو</span><span class="mono">{{ $fmt($r['balance']['trx_staked']) }}</span></div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Flows --}}
        @if ($r['flows'])
            <div class="bg-white rounded-2xl shadow-sm p-5 mb-5">
                <h2 class="font-bold text-lg mb-3">کۆی هاتوو و ڕۆیشتوو</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-slate-500"><tr class="border-b"><th class="text-right py-2">دراو</th><th class="text-right">⬇️ هاتوو</th><th class="text-right">⬆️ ڕۆیشتوو</th></tr></thead>
                        <tbody>
                        @foreach ($r['flows'] as $f)
                            <tr class="border-b last:border-0">
                                <td class="py-2 font-semibold">{{ $f['token'] }}</td>
                                <td class="mono text-emerald-700">{{ $fmt($f['in']) }} <span class="text-slate-400">({{ $f['in_count'] }})</span></td>
                                <td class="mono text-red-700">{{ $fmt($f['out']) }} <span class="text-slate-400">({{ $f['out_count'] }})</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Exchanges --}}
        <div class="bg-white rounded-2xl shadow-sm p-5 mb-5">
            <h2 class="font-bold text-lg mb-3">🏦 پەیوەندی بە ئێکسچەینجەکانەوە</h2>
            @forelse ($r['exchanges'] as $e)
                <div class="flex flex-wrap justify-between gap-2 py-2 border-b last:border-0">
                    <span class="font-bold">{{ $e['exchange'] }}</span>
                    <span class="mono text-sm">
                        @if ($e['in'])<span class="text-emerald-700">⬇️ {{ $fmt($e['in']) }} USDT لێوەی هاتووە</span>@endif
                        @if ($e['in'] && $e['out']) · @endif
                        @if ($e['out'])<span class="text-red-700">⬆️ {{ $fmt($e['out']) }} USDT بۆی چووە</span>@endif
                    </span>
                </div>
            @empty
                <p class="text-slate-400 text-sm">
                    @if ($r['tronscan_enabled'])
                        هیچ ئێکسچەینجێک لە نێو گەورەترین لایەنەکاندا نەدۆزرایەوە — بەگشتی والێتی تایبەتن.
                    @else
                        بۆ ناسینەوەی Binance، OKX، HTX و ئەوانی تر، TRONSCAN_API_KEY لە فایلی .env دابنێ (بێبەرامبەرە لە tronscan.org).
                    @endif
                </p>
            @endforelse
        </div>

        {{-- Counterparties --}}
        <div class="grid md:grid-cols-2 gap-4 mb-5">
            @foreach ([['senders', 'in', 'لە کوێوە پارەی بۆ هاتووە', 'text-emerald-700'], ['receivers', 'out', 'بۆ کوێ ڕۆیشتووە', 'text-red-700']] as [$key, $dir, $title, $color])
                <div class="bg-white rounded-2xl shadow-sm p-5">
                    <h2 class="font-bold text-lg mb-3">{{ $title }}</h2>
                    @forelse ($r[$key] as $c)
                        <div class="py-2 border-b last:border-0">
                            <div class="flex justify-between gap-2 items-center">
                                <a href="{{ route('scan', ['address' => $c['address']]) }}" class="mono text-blue-700 hover:underline" title="{{ $c['address'] }}">{{ $short($c['address']) }}</a>
                                <span class="text-xs">
                                    @foreach ($c['flags'] ?? (($c['blacklisted'] ?? false) ? ['tether'] : []) as $flag)
                                        <span class="bg-red-100 text-red-700 rounded px-2 py-0.5" title="{{ \App\Models\FlaggedAddress::SOURCES[$flag] ?? $flag }}">⛔ {{ ['tether' => 'Tether بلاک‌لیست', 'ofac' => 'OFAC', 'manual' => 'لیستی خۆت'][$flag] ?? $flag }}</span>
                                    @endforeach
                                    @if ($c['exchange'] ?? null)
                                        <span class="bg-yellow-100 text-yellow-800 font-semibold rounded px-2 py-0.5">🏦 {{ $c['exchange'] }}{{ $c['exchange_kind'] === 'deposit' ? ' · دیپۆزیت' : '' }}</span>
                                    @elseif ($c['label']['name'] ?? null)
                                        <span class="bg-blue-100 text-blue-700 rounded px-2 py-0.5">{{ $c['label']['name'] }}</span>
                                    @endif
                                    @if ($c['label']['red'] ?? null)<span class="bg-red-100 text-red-700 rounded px-2 py-0.5">{{ $c['label']['red'] }}</span>@endif
                                </span>
                            </div>
                            <div class="text-sm mono {{ $color }}">{{ $amounts($c[$dir]) }}</div>
                            <div class="text-xs text-slate-400">{{ $c[$dir.'_count'] }} مامەڵە · دوایین: {{ $c['last'] }}
                                @if (! ($c['exchange'] ?? null) && ($c['forwards_to'] ?? null))
                                    · پارەکە دەنێرێت بۆ <a href="{{ route('scan', ['address' => $c['forwards_to']]) }}" class="mono hover:underline">{{ $short($c['forwards_to']) }}</a>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-slate-400 text-sm">هیچ مامەڵەیەک نییە.</p>
                    @endforelse
                </div>
            @endforeach
        </div>

        {{-- Recent --}}
        @if ($r['recent'])
            @php
                // names of known counterparties, to show next to each transfer
                $names = [];
                foreach (array_merge($r['senders'], $r['receivers']) as $c) {
                    $names[$c['address']] = ($c['exchange'] ?? null)
                        ? $c['exchange'].(($c['exchange_kind'] ?? null) === 'deposit' ? ' · دیپۆزیت' : '')
                        : ($c['label']['name'] ?? null);
                }
                $tokens = array_values(array_unique(array_column($r['recent'], 'token')));
            @endphp
            <div class="bg-white rounded-2xl shadow-sm p-5 mb-5" id="recent">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <h2 class="font-bold text-lg">دوایین مامەڵەکان <span class="text-sm font-normal text-slate-400">({{ count($r['recent']) }})</span></h2>
                    <div class="flex flex-wrap gap-1 text-sm" id="recent-filters">
                        <button type="button" data-dir="all" class="rf-dir px-3 py-1 rounded-lg bg-slate-800 text-white">هەمووی</button>
                        <button type="button" data-dir="in" class="rf-dir px-3 py-1 rounded-lg bg-slate-100">⬇️ هاتوو</button>
                        <button type="button" data-dir="out" class="rf-dir px-3 py-1 rounded-lg bg-slate-100">⬆️ ڕۆیشتوو</button>
                        <select id="rf-token" class="px-2 py-1 rounded-lg bg-slate-100">
                            <option value="all">هەموو دراوەکان</option>
                            @foreach ($tokens as $tk) <option value="{{ $tk }}">{{ $tk }}</option> @endforeach
                        </select>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm whitespace-nowrap">
                        <thead class="text-slate-500"><tr class="border-b"><th class="text-right py-2">کات</th><th class="text-right">ئاراستە</th><th class="text-right">لایەنی بەرامبەر</th><th class="text-right">بڕ</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($r['recent'] as $i => $t)
                            @php $in = $t['to'] === $r['address']; $other = $in ? $t['from'] : $t['to']; @endphp
                            <tr class="rt border-b last:border-0" data-dir="{{ $in ? 'in' : 'out' }}" data-token="{{ $t['token'] }}">
                                <td class="py-2">{{ $t['time'] }}</td>
                                <td class="{{ $in ? 'text-emerald-700' : 'text-red-700' }}">{{ $in ? '⬇️ هاتوو' : '⬆️ ڕۆیشتوو' }}</td>
                                <td>
                                    <a href="{{ route('scan', ['address' => $other]) }}" class="mono text-blue-700 hover:underline" title="{{ $other }}">{{ $short($other) }}</a>
                                    @if ($names[$other] ?? null)<span class="text-xs bg-yellow-100 text-yellow-800 rounded px-1.5 py-0.5 mr-1">{{ $names[$other] }}</span>@endif
                                </td>
                                <td class="mono font-semibold">{{ $fmt($t['amount']) }} {{ $t['token'] }}</td>
                                <td><a href="https://tronscan.org/#/transaction/{{ $t['tx'] }}" target="_blank" rel="noopener" class="text-xs text-slate-400 hover:text-blue-600">Tronscan ↗</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-3">
                    <button type="button" id="rf-more" class="hidden px-5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 font-semibold">زیاتر پیشان بدە</button>
                </div>
            </div>
            <script>
                (function () {
                    const PAGE = 50;
                    let dir = 'all', token = 'all', shown = PAGE;
                    const rows = [...document.querySelectorAll('#recent .rt')];
                    const more = document.getElementById('rf-more');
                    function render() {
                        const match = rows.filter(r => (dir === 'all' || r.dataset.dir === dir) && (token === 'all' || r.dataset.token === token));
                        rows.forEach(r => r.classList.add('hidden'));
                        match.slice(0, shown).forEach(r => r.classList.remove('hidden'));
                        more.classList.toggle('hidden', match.length <= shown);
                        more.textContent = 'زیاتر پیشان بدە (' + (match.length - shown) + ' ی تر)';
                    }
                    document.querySelectorAll('.rf-dir').forEach(b => b.addEventListener('click', () => {
                        dir = b.dataset.dir; shown = PAGE;
                        document.querySelectorAll('.rf-dir').forEach(x => x.className = 'rf-dir px-3 py-1 rounded-lg ' + (x === b ? 'bg-slate-800 text-white' : 'bg-slate-100'));
                        render();
                    }));
                    document.getElementById('rf-token').addEventListener('change', e => { token = e.target.value; shown = PAGE; render(); });
                    more.addEventListener('click', () => { shown += PAGE; render(); });
                    render();
                })();
            </script>
        @endif

        <p class="text-center text-xs text-slate-400">
            سکان کرا لە {{ $r['scanned_at'] }} ·
            <a class="hover:underline" target="_blank" rel="noopener" href="https://tronscan.org/#/address/{{ $r['address'] }}">بینین لە Tronscan ↗</a>
        </p>
    @endif
@endsection
