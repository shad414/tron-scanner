@extends('layout')

@section('content')
@php
    $fmt = fn ($n) => rtrim(rtrim(number_format($n, 6, '.', ','), '0'), '.') ?: '0';
    $money = fn ($n) => number_format($n, $n >= 1000 ? 0 : 2);
    $short = fn ($a) => substr($a, 0, 6).'…'.substr($a, -5);
    $amounts = fn ($m) => collect($m)->map(fn ($v, $k) => $fmt($v).' '.$k)->implode(' · ');
    $riskTone = ['ok', 'warn', 'bad'];
    $riskIcon = ['✓', '!', '✕'];
    $isAdmin = \App\Http\Middleware\RequireAdmin::isAdmin(request());
@endphp

    @unless ($result)
        <section class="text-center pt-4 pb-6">
            <div class="inline-flex items-center gap-2 pill pill-blue mb-4">🛡️ پشکنینی AML بۆ ئادرەسەکانی TRON</div>
            <h1 class="text-[28px] sm:text-4xl font-extrabold leading-snug">پێش وەرگرتنی پارە،<br><span style="color: var(--brand)">ئادرەسەکە بپشکنە</span></h1>
            <p class="muted mt-3 text-[15px]">باڵانس، سەرچاوەی پارە، ئێکسچەینجەکان و بلاک‌لیستی Tether و OFAC — لە چەند چرکەیەکدا</p>
        </section>
    @endunless

    {{-- Search --}}
    <form method="get" action="{{ route('scan') }}" id="scanForm" class="no-print card p-3 mb-4" autocomplete="off">
        <div class="relative">
            <input id="addr" name="address" value="{{ $address }}" required placeholder="ئادرەسی TRON — T…"
                   class="field mono !text-[15px] !py-3.5 !pl-24" spellcheck="false" autocapitalize="off" autocorrect="off" enterkeyhint="search">
            <button type="button" id="pasteBtn" class="btn !py-1.5 !px-3 !text-[13px] !rounded-[10px] absolute left-2 top-1/2 -translate-y-1/2">📋 لکاندن</button>
            <button type="button" id="clearBtn" class="hidden absolute left-2 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full grid place-items-center muted" aria-label="سڕینەوە">✕</button>
        </div>
        <p id="addrHint" class="hidden text-xs mt-2 px-1 c-warn">ئادرەسی TRON بە پیتی T دەست پێدەکات و ٣٤ پیتە.</p>
        <button class="btn btn-primary w-full mt-3 !py-3.5 !text-[16px]">
            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            پشکنین
        </button>
    </form>

    @unless ($result)
        <div id="recentWrap" class="no-print hidden mb-6">
            <div class="flex items-center justify-between mb-2 px-1">
                <span class="text-sm font-bold muted">دوایین پشکنینەکانت</span>
                <button type="button" id="recentClear" class="text-xs faint">سڕینەوە</button>
            </div>
            <div id="recentList" class="space-y-2"></div>
        </div>
        <div class="grid grid-cols-3 gap-2 text-center mb-4">
            @foreach ([['⛔', 'بلاک‌لیست', 'Tether · OFAC'], ['🏦', 'ئێکسچەینج', 'Binance · OKX · HTX'], ['🧪', 'سەرچاوەی پارە', 'تا ٢ هەنگاو']] as [$i, $t, $s])
                <div class="card p-3">
                    <div class="text-2xl">{{ $i }}</div>
                    <div class="font-bold text-[13px] mt-1">{{ $t }}</div>
                    <div class="text-[11px] faint mt-0.5">{{ $s }}</div>
                </div>
            @endforeach
        </div>
    @endunless

    {{-- Loading overlay --}}
    <div id="loading" class="hidden fixed inset-0 z-50 grid place-items-center p-6" style="background: rgba(7,11,16,.86); backdrop-filter: blur(8px)">
        <div class="text-center max-w-xs">
            <div class="relative w-20 h-20 mx-auto mb-5">
                <div class="absolute inset-0 rounded-full border-4" style="border-color: rgba(45,212,191,.15)"></div>
                <div class="absolute inset-0 rounded-full border-4 border-transparent animate-spin" style="border-top-color: var(--brand)"></div>
                <div class="absolute inset-0 grid place-items-center text-2xl">₮</div>
            </div>
            <p class="font-extrabold text-lg">سکان دەکرێت…</p>
            <p id="loadStep" class="muted text-sm mt-1 min-h-[1.5em]">وەرگرتنی مامەڵەکان</p>
            <p class="faint text-xs mt-4">زۆربەی جار ٥–١٥ چرکە دەخایەنێت</p>
        </div>
    </div>

    @if ($error)
        <div class="card p-4 mb-4 flex gap-3 items-start" style="border-color: rgba(248,113,113,.4)">
            <span class="text-xl">⚠️</span><div class="c-bad font-semibold">{{ $error }}</div>
        </div>
    @endif

    @if ($result)
        @php
            $r = $result; $risk = $r['risk']; $tone = $riskTone[$risk['level']];
            $usdt = collect($r['balance']['tokens'])->firstWhere('token', 'USDT')['amount'] ?? 0;
            $txCount = $r['analyzed']['trc20'] + $r['analyzed']['trx'];
            $taintIn = $r['taint']['in']['percent'] ?? 0;
            $who = $r['exchange']
                ? '🏦 '.$r['exchange'].' — '.($r['exchange_kind'] === 'deposit' ? 'ئادرەسی دیپۆزیت' : ($r['label']['name'] ?? 'والێتی ئێکسچەینج'))
                : (($r['label']['name'] ?? null) ? '🏦 '.$r['label']['name'] : null);
        @endphp

        {{-- Verdict --}}
        <section class="card overflow-hidden mb-4 print-break" style="border-color: color-mix(in srgb, var(--{{ $tone }}) 45%, transparent)">
            <div class="p-5" style="background: linear-gradient(160deg, var(--{{ $tone }}-bg), transparent 70%)">
                <div class="flex items-start gap-4">
                    <div class="shrink-0 w-14 h-14 rounded-2xl grid place-items-center text-2xl font-extrabold"
                         style="background: var(--{{ $tone }}); color: #071017; box-shadow: 0 10px 26px color-mix(in srgb, var(--{{ $tone }}) 35%, transparent)">{{ $riskIcon[$risk['level']] }}</div>
                    <div class="min-w-0">
                        <div class="text-[19px] font-extrabold leading-snug c-{{ $tone }}">{{ $risk['text'] }}</div>
                        @if ($who)<div class="mt-1.5 text-sm font-semibold c-gold">{{ $who }}</div>@endif
                    </div>
                </div>

                <button type="button" data-copy="{{ $r['address'] }}" class="no-print mt-4 w-full flex items-center gap-2 rounded-xl px-3 py-2.5 text-right" style="background: rgba(0,0,0,.25); border: 1px solid var(--line)">
                    <span class="mono text-[12.5px] break-all flex-1 text-left">{{ $r['address'] }}</span>
                    <span class="text-xs muted shrink-0">📋 کۆپی</span>
                </button>
                <div class="hidden print:block mono text-sm mt-3">{{ $r['address'] }}</div>

                @if ($risk['reasons'])
                    <ul class="mt-4 space-y-2">
                        @foreach ($risk['reasons'] as $reason)
                            <li class="flex gap-2 text-[14px]"><span class="c-{{ $tone }}">●</span><span>{{ $reason }}</span></li>
                        @endforeach
                    </ul>
                @endif
                @if ($risk['notes'])
                    <details class="mt-3 rounded-xl px-3 py-2" style="background: rgba(0,0,0,.2)">
                        <summary class="cursor-pointer text-sm font-semibold muted">ℹ️ تێبینی ({{ count($risk['notes']) }})</summary>
                        <ul class="mt-2 space-y-1.5 text-[13px] muted">
                            @foreach ($risk['notes'] as $note) <li>• {{ $note }}</li> @endforeach
                        </ul>
                    </details>
                @endif
            </div>

            {{-- Key numbers --}}
            <div class="grid grid-cols-2 sm:grid-cols-4" style="border-top: 1px solid var(--line)">
                @foreach ([
                    [$money($usdt), 'USDT', 'باڵانس', ''],
                    [$money($r['balance']['trx']), 'TRX', 'باڵانس', ''],
                    [number_format($txCount), 'مامەڵە', 'شیکراوەتەوە', ''],
                    [$taintIn.'٪', 'سەرچاوەی پیس', 'پارەی هاتوو', $taintIn >= 10 ? 'c-bad' : ($taintIn > 0 ? 'c-warn' : 'c-ok')],
                ] as $i => [$big, $label, $sub, $cls])
                    <div class="p-3.5 text-center" style="{{ $i % 2 ? '' : 'border-left: 1px solid var(--line);' }} {{ $i < 2 ? 'border-bottom: 1px solid var(--line);' : '' }}">
                        @if ($i < 2)
                            <div class="mono text-[19px] font-extrabold">{{ $big }}</div>
                            <div class="text-[11.5px] faint mt-0.5">{{ $label }} {{ $sub }}</div>
                        @else
                            <div class="text-[19px] font-extrabold {{ $cls }}">{{ $big }}</div>
                            <div class="text-[11.5px] faint mt-0.5">{{ $label }} · {{ $sub }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Actions --}}
        @php $shareUrl = ($scan && $isAdmin) ? route('history.show', $scan) : route('scan', ['address' => $r['address']]); @endphp
        <div class="no-print hscroll mb-4">
            <button type="button" class="btn !rounded-full !py-2" id="shareBtn" data-url="{{ $shareUrl }}">↗ هاوبەشکردن</button>
            <button type="button" onclick="window.print()" class="btn !rounded-full !py-2">🖨️ PDF</button>
            @if ($isAdmin)
                <button type="button" class="btn !rounded-full !py-2" onclick="document.getElementById('watchPanel').toggleAttribute('open')">
                    🔔 چاودێری @if ($watches->count()) <span class="pill pill-ok">{{ $watches->count() }}</span> @endif
                </button>
            @endif
            <a href="https://tronscan.org/#/address/{{ $r['address'] }}" target="_blank" rel="noopener" class="btn !rounded-full !py-2">Tronscan ↗</a>
            @if ($scan && $isAdmin)
                <a href="{{ route('history.show', $scan) }}" class="btn !rounded-full !py-2">🔗 ڕاپۆرت #{{ $scan->id }}</a>
            @endif
        </div>

        @if ($scan && $scan->created_at->lt(now()->subMinutes(10)))
            <div class="no-print card p-3 mb-4 text-sm c-warn flex flex-wrap gap-2 items-center justify-between">
                <span>ڕاپۆرتی هەڵگیراوە لە {{ $scan->created_at->format('Y-m-d H:i') }}</span>
                <a class="btn !py-1.5 !text-[13px]" href="{{ route('scan', ['address' => $r['address']]) }}">↻ سکانی نوێ</a>
            </div>
        @endif

        @if ($isAdmin)
            <details id="watchPanel" class="no-print">
                <summary class="hidden"></summary>
                <form method="post" action="{{ route('watches.store') }}" class="card p-4 mb-4 space-y-3">
                    @csrf
                    <input type="hidden" name="address" value="{{ $r['address'] }}">
                    <div class="font-bold">🔔 چاودێری ئەم ئادرەسە بە Telegram</div>
                    <p class="text-xs muted">هەر کاتێک پارە هات یان ڕۆیشت، نامەت بۆ دێت. Chat ID ت لە <span class="mono">@userinfobot</span> وەربگرە.</p>
                    <input name="chat_id" required inputmode="numeric" placeholder="Telegram Chat ID" class="field mono">
                    <input name="label" maxlength="100" placeholder="ناو (ئارەزوومەندانە) — بۆ نموونە: والێتی کڕیار" class="field">
                    <div class="flex gap-2">
                        <button class="btn btn-primary flex-1">چاودێری بکە</button>
                        <a href="{{ route('watches') }}" class="btn">ڕێکخستن</a>
                    </div>
                </form>
            </details>
        @endif

        {{-- Source of funds (taint) --}}
        @isset($r['taint'])
            <details class="card sec print-break" open>
                <summary><span class="sec-icon">🧪</span>سەرچاوەی پارە <span class="pill pill-mute">AML · ٢ هەنگاو</span><span class="chev">⌄</span></summary>
                <div class="body">
                    <p class="text-xs faint mb-4">بەراورد کراوە لەگەڵ {{ number_format(\App\Models\FlaggedAddress::count()) }} ئادرەسی بلاک‌لیستی Tether، سزاکانی OFAC و لیستی خۆت. ئێکسچەینجەکان زنجیرەکە دەبڕن.</p>
                    <div class="grid sm:grid-cols-2 gap-5">
                        @foreach (['in' => ['⬇️ پارە هاتووەکان', 'لە سەرچاوەی پیسەوە'], 'out' => ['⬆️ پارە ڕۆیشتووەکان', 'گەیشتووە بە ئادرەسی پیس']] as $dir => [$title, $sub])
                            @php $t = $r['taint'][$dir]; $c = $t['percent'] >= 10 ? 'bad' : ($t['percent'] > 0 ? 'warn' : 'ok'); @endphp
                            <div>
                                <div class="flex justify-between items-baseline">
                                    <span class="font-semibold text-[14px]">{{ $title }}</span>
                                    <span class="text-2xl font-extrabold c-{{ $c }}">{{ $t['percent'] }}٪</span>
                                </div>
                                <div class="h-2.5 rounded-full overflow-hidden my-2" style="background: rgba(255,255,255,.06)">
                                    <div class="h-full rounded-full" style="width: {{ max($t['percent'], $t['percent'] > 0 ? 2 : 0) }}%; background: var(--{{ $c }})"></div>
                                </div>
                                <div class="text-xs faint">{{ $sub }} · {{ $t['coverage'] }}٪ ی بڕەکە شیکرایەوە</div>
                                @foreach ($t['paths'] as $p)
                                    <div class="mt-2.5 rounded-xl p-3" style="background: var(--bad-bg); border: 1px solid rgba(248,113,113,.25)">
                                        <div class="mono text-[12px] break-all leading-6">
                                            @php $chain = $dir === 'in' ? [...$p['hops'], $r['address']] : [$r['address'], ...$p['hops']]; @endphp
                                            @foreach ($chain as $i => $hop)
                                                @if ($i) <span class="faint">→</span> @endif
                                                <a href="{{ route('scan', ['address' => $hop]) }}" class="{{ $hop === $r['address'] ? 'font-bold' : 'c-blue' }}" title="{{ $hop }}">{{ $short($hop) }}</a>
                                            @endforeach
                                        </div>
                                        <div class="flex justify-between items-center mt-1.5 gap-2">
                                            <span class="c-bad text-xs">{{ collect($p['sources'])->map(fn ($s) => \App\Models\FlaggedAddress::SOURCES[$s] ?? $s)->implode('، ') }}</span>
                                            <span class="mono font-bold text-[13px]">{{ $fmt(round($p['amount'], 2)) }} USDT</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            </details>
        @endisset

        {{-- Flow graph --}}
        @include('partials.flow-graph', ['r' => $r, 'short' => $short, 'fmt' => $fmt])

        {{-- Exchanges --}}
        <details class="card sec" open>
            <summary><span class="sec-icon">🏦</span>ئێکسچەینجەکان @if (count($r['exchanges'])) <span class="pill pill-gold">{{ count($r['exchanges']) }}</span> @endif<span class="chev">⌄</span></summary>
            <div class="body">
                @forelse ($r['exchanges'] as $e)
                    <div class="row">
                        <span class="font-bold c-gold">{{ $e['exchange'] }}</span>
                        <span class="mono text-[13px] text-left">
                            @if ($e['in'])<span class="c-ok block">⬇️ {{ $fmt($e['in']) }} USDT لێوەی هاتووە</span>@endif
                            @if ($e['out'])<span class="c-bad block">⬆️ {{ $fmt($e['out']) }} USDT بۆی چووە</span>@endif
                        </span>
                    </div>
                @empty
                    <p class="muted text-sm">
                        @if ($r['tronscan_enabled'])
                            هیچ ئێکسچەینجێک لە نێو گەورەترین لایەنەکاندا نەدۆزرایەوە — بەگشتی والێتی تایبەتن.
                        @else
                            بۆ ناسینەوەی Binance، OKX، HTX و ئەوانی تر، TRONSCAN_API_KEY لە فایلی .env دابنێ (بێبەرامبەرە لە tronscan.org).
                        @endif
                    </p>
                @endforelse
            </div>
        </details>

        {{-- Details --}}
        <details class="card sec">
            <summary><span class="sec-icon">📋</span>زانیاری و باڵانس<span class="chev">⌄</span></summary>
            <div class="body text-[14px]">
                <div class="row"><span class="muted">خاوەن / جۆر</span>
                    <span class="font-semibold text-left">
                        @if ($r['exchange'])
                            🏦 {{ $r['exchange'] }} — {{ $r['exchange_kind'] === 'deposit' ? 'ئادرەسی دیپۆزیت (هی بەکارهێنەرێکی ئێکسچەینجە)' : ($r['label']['name'] ?? 'والێتی ئێکسچەینج') }}
                        @elseif ($r['label']['name'] ?? null)
                            🏦 {{ $r['label']['name'] }}
                        @elseif ($r['tronscan_enabled'])
                            والێتی تایبەت (ناسناوی گشتی نییە)
                        @else
                            <span class="faint">نەزانراو — TRONSCAN_API_KEY دابنێ</span>
                        @endif
                    </span></div>
                <div class="row"><span class="muted">بلاک‌لیستی USDT (Tether)</span>
                    @if ($r['usdt_blacklisted'] === null) <span class="pill pill-mute">نەپشکنرا</span>
                    @elseif ($r['usdt_blacklisted']) <span class="pill pill-bad">بەڵێ ⛔</span>
                    @else <span class="pill pill-ok">نەخێر ✓</span> @endif
                </div>
                @if ($r['security'])
                    <div class="row"><span class="muted">لیستی ڕەشی Tronscan</span>
                        <span class="pill {{ $r['security']['blacklist'] ? 'pill-bad' : 'pill-ok' }}">{{ $r['security']['blacklist'] ? 'بەڵێ ⛔' : 'نەخێر ✓' }}</span></div>
                @endif
                <div class="row"><span class="muted">دروستکراوە لە</span><span class="mono text-[13px]">{{ $r['created_at'] ?? '—' }}</span></div>
                <div class="row"><span class="muted">دوایین چالاکی</span><span class="mono text-[13px]">{{ $r['last_activity'] ?? '—' }}</span></div>
                <div class="row"><span class="muted">مامەڵە شیکراوەکان</span><span>{{ $r['analyzed']['trc20'] }} TRC20 · {{ $r['analyzed']['trx'] }} TRX</span></div>

                <div class="font-bold mt-5 mb-1">باڵانس (چەندی لەسەرە)</div>
                @foreach ($r['balance']['tokens'] as $t)
                    <div class="row"><span class="font-semibold">{{ $t['token'] }}</span><span class="mono text-[16px] font-bold">{{ $fmt($t['amount']) }}</span></div>
                @endforeach
                <div class="row"><span class="font-semibold">TRX</span><span class="mono text-[16px] font-bold">{{ $fmt($r['balance']['trx']) }}</span></div>
                @if ($r['balance']['trx_staked'] > 0)
                    <div class="row"><span class="muted">TRX ی ستەیک کراو</span><span class="mono">{{ $fmt($r['balance']['trx_staked']) }}</span></div>
                @endif

                @if ($r['flows'])
                    <div class="font-bold mt-5 mb-1">کۆی هاتوو و ڕۆیشتوو</div>
                    @foreach ($r['flows'] as $f)
                        <div class="row">
                            <span class="font-semibold">{{ $f['token'] }}</span>
                            <span class="mono text-[13px] text-left">
                                <span class="c-ok block">⬇️ {{ $fmt($f['in']) }} <span class="faint">({{ $f['in_count'] }})</span></span>
                                <span class="c-bad block">⬆️ {{ $fmt($f['out']) }} <span class="faint">({{ $f['out_count'] }})</span></span>
                            </span>
                        </div>
                    @endforeach
                @endif
            </div>
        </details>

        {{-- Counterparties --}}
        @foreach ([['senders', 'in', 'لە کوێوە پارەی بۆ هاتووە', 'c-ok', '⬇️'], ['receivers', 'out', 'بۆ کوێ ڕۆیشتووە', 'c-bad', '⬆️']] as [$key, $dir, $title, $color, $icon])
            <details class="card sec">
                <summary><span class="sec-icon">{{ $icon }}</span>{{ $title }} <span class="pill pill-mute">{{ count($r[$key]) }}</span><span class="chev">⌄</span></summary>
                <div class="body">
                    @forelse ($r[$key] as $c)
                        <a href="{{ route('scan', ['address' => $c['address']]) }}" class="row !items-start">
                            <span class="min-w-0">
                                <span class="mono c-blue text-[13px]" title="{{ $c['address'] }}">{{ $short($c['address']) }}</span>
                                <span class="flex flex-wrap gap-1 mt-1">
                                    @foreach ($c['flags'] ?? (($c['blacklisted'] ?? false) ? ['tether'] : []) as $flag)
                                        <span class="pill pill-bad" title="{{ \App\Models\FlaggedAddress::SOURCES[$flag] ?? $flag }}">⛔ {{ ['tether' => 'Tether بلاک‌لیست', 'ofac' => 'OFAC', 'manual' => 'لیستی خۆت'][$flag] ?? $flag }}</span>
                                    @endforeach
                                    @if ($c['exchange'] ?? null)
                                        <span class="pill pill-gold">🏦 {{ $c['exchange'] }}{{ $c['exchange_kind'] === 'deposit' ? ' · دیپۆزیت' : '' }}</span>
                                    @elseif ($c['label']['name'] ?? null)
                                        <span class="pill pill-blue">{{ $c['label']['name'] }}</span>
                                    @endif
                                    @if ($c['label']['red'] ?? null)<span class="pill pill-bad">{{ $c['label']['red'] }}</span>@endif
                                </span>
                                <span class="block text-[11.5px] faint mt-1">{{ $c[$dir.'_count'] }} مامەڵە · دوایین: {{ $c['last'] }}
                                    @if (! ($c['exchange'] ?? null) && ($c['forwards_to'] ?? null))
                                        · دەینێرێت بۆ <span class="mono">{{ $short($c['forwards_to']) }}</span>
                                    @endif
                                </span>
                            </span>
                            <span class="mono text-[13px] font-bold {{ $color }} text-left shrink-0">{!! str_replace(' · ', '<br>', e($amounts($c[$dir]))) !!}</span>
                        </a>
                    @empty
                        <p class="muted text-sm">هیچ مامەڵەیەک نییە.</p>
                    @endforelse
                </div>
            </details>
        @endforeach

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
            <details class="card sec" id="recent" open>
                <summary><span class="sec-icon">🧾</span>دوایین مامەڵەکان <span class="pill pill-mute">{{ count($r['recent']) }}</span><span class="chev">⌄</span></summary>
                <div class="body">
                    <div class="no-print flex gap-2 mb-2" id="recent-filters">
                        <div class="seg flex-1">
                            <button type="button" data-dir="all" class="rf-dir on">هەمووی</button>
                            <button type="button" data-dir="in" class="rf-dir">⬇️ هاتوو</button>
                            <button type="button" data-dir="out" class="rf-dir">⬆️ ڕۆیشتوو</button>
                        </div>
                        <select id="rf-token" class="field !w-auto !py-1.5 !px-2 !text-[13px] !rounded-xl">
                            <option value="all">هەموو</option>
                            @foreach ($tokens as $tk) <option value="{{ $tk }}">{{ $tk }}</option> @endforeach
                        </select>
                    </div>
                    <div>
                        @foreach ($r['recent'] as $i => $t)
                            @php $in = $t['to'] === $r['address']; $other = $in ? $t['from'] : $t['to']; @endphp
                            <div class="rt row" data-dir="{{ $in ? 'in' : 'out' }}" data-token="{{ $t['token'] }}">
                                <span class="shrink-0 w-9 h-9 rounded-xl grid place-items-center text-[15px]" style="background: var(--{{ $in ? 'ok' : 'bad' }}-bg); color: var(--{{ $in ? 'ok' : 'bad' }})">{{ $in ? '↓' : '↑' }}</span>
                                <span class="min-w-0 flex-1">
                                    <a href="{{ route('scan', ['address' => $other]) }}" class="mono c-blue text-[13px]" title="{{ $other }}">{{ $short($other) }}</a>
                                    @if ($names[$other] ?? null)<span class="pill pill-gold !text-[11px] mr-1">{{ $names[$other] }}</span>@endif
                                    <span class="block text-[11.5px] faint mono" style="direction: rtl">{{ $t['time'] }} · {{ $in ? 'هاتوو' : 'ڕۆیشتوو' }}</span>
                                </span>
                                <a href="https://tronscan.org/#/transaction/{{ $t['tx'] }}" target="_blank" rel="noopener" class="text-left shrink-0">
                                    <span class="mono font-bold text-[14px] block {{ $in ? 'c-ok' : 'c-bad' }}">{{ $in ? '+' : '−' }}{{ $fmt($t['amount']) }}</span>
                                    <span class="text-[11px] faint">{{ $t['token'] }} ↗</span>
                                </a>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" id="rf-more" class="no-print hidden btn w-full mt-3">زیاتر پیشان بدە</button>
                </div>
            </details>
            @push('scripts')
            <script>
                (function () {
                    const PAGE = 30;
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
                        document.querySelectorAll('.rf-dir').forEach(x => x.classList.toggle('on', x === b));
                        render();
                    }));
                    document.getElementById('rf-token').addEventListener('change', e => { token = e.target.value; shown = PAGE; render(); });
                    more.addEventListener('click', () => { shown += PAGE; render(); });
                    render();
                })();
            </script>
            @endpush
        @endif

        <p class="text-center text-xs faint mt-6">
            سکان کرا لە {{ $r['scanned_at'] }} · ئەم هەڵسەنگاندنە لەسەر بنەمای داتای گشتی بلۆکچەینە و گەرەنتی تەواو نییە.
        </p>
    @endif

    @push('scripts')
    <script>
        (function () {
            const form = document.getElementById('scanForm'), input = document.getElementById('addr');
            const paste = document.getElementById('pasteBtn'), clear = document.getElementById('clearBtn'), hint = document.getElementById('addrHint');
            const valid = v => /^T[1-9A-HJ-NP-Za-km-z]{33}$/.test(v);
            const sync = () => {
                const v = input.value.trim();
                paste.classList.toggle('hidden', v !== '');
                clear.classList.toggle('hidden', v === '');
                hint.classList.toggle('hidden', v === '' || valid(v) || v.length < 34);
            };
            const go = () => {
                input.value = input.value.trim();
                document.getElementById('loading').classList.remove('hidden');
                form.querySelector('.btn-primary').disabled = true;
                const steps = ['وەرگرتنی مامەڵەکان', 'ناسینەوەی ئێکسچەینجەکان', 'پشکنینی بلاک‌لیستی Tether و OFAC', 'شیکردنەوەی سەرچاوەی پارە', 'ئامادەکردنی ڕاپۆرت'];
                let i = 0; const el = document.getElementById('loadStep');
                setInterval(() => { i = Math.min(i + 1, steps.length - 1); el.textContent = steps[i]; }, 2600);
            };
            input.addEventListener('input', sync);
            input.addEventListener('paste', () => setTimeout(() => { sync(); if (valid(input.value.trim())) { go(); form.submit(); } }, 0));
            clear.addEventListener('click', () => { input.value = ''; sync(); input.focus(); });
            paste.addEventListener('click', async () => {
                try {
                    const t = (await navigator.clipboard.readText()).trim();
                    input.value = t; sync();
                    if (valid(t)) { go(); form.submit(); } else { toast('ئەوەی کۆپیت کردووە ئادرەسی TRON نییە'); }
                } catch { input.focus(); toast('دەست لەسەر خانەکە ڕابگرە و Paste بکە'); }
            });
            form.addEventListener('submit', go);
            window.addEventListener('pageshow', () => { document.getElementById('loading').classList.add('hidden'); form.querySelector('.btn-primary').disabled = false; });
            sync();

            // recent searches stay on this device only
            const KEY = 'tron-recent';
            const load = () => { try { return JSON.parse(localStorage.getItem(KEY)) || []; } catch { return []; } };
            const save = list => { try { localStorage.setItem(KEY, JSON.stringify(list)); } catch {} };
            @if ($result)
                save([{ a: @json($result['address']), l: {{ $result['risk']['level'] }}, t: Date.now() }, ...load().filter(x => x.a !== @json($result['address']))].slice(0, 6));
            @endif
            const wrap = document.getElementById('recentWrap');
            if (wrap) {
                const list = load();
                const tone = ['ok', 'warn', 'bad'], icon = ['✓', '!', '✕'];
                if (list.length) {
                    wrap.classList.remove('hidden');
                    document.getElementById('recentList').innerHTML = list.map(x =>
                        `<a href="?address=${encodeURIComponent(x.a)}" class="card flex items-center gap-3 px-3.5 py-3">
                            <span class="w-8 h-8 rounded-lg grid place-items-center font-extrabold text-sm" style="background: var(--${tone[x.l]}-bg); color: var(--${tone[x.l]})">${icon[x.l]}</span>
                            <span class="mono text-[13px] flex-1 text-left">${x.a.slice(0, 8)}…${x.a.slice(-6)}</span>
                            <span class="faint">‹</span></a>`).join('');
                }
                document.getElementById('recentClear').addEventListener('click', () => { save([]); wrap.classList.add('hidden'); });
            }

            const share = document.getElementById('shareBtn');
            if (share) share.addEventListener('click', async () => {
                const url = share.dataset.url;
                if (navigator.share) { try { await navigator.share({ title: 'پشکنینی ئادرەسی TRON', url }); } catch {} }
                else { try { await navigator.clipboard.writeText(url); toast('بەستەر کۆپی کرا ✓'); } catch {} }
            });
        })();
    </script>
    @endpush
@endsection
