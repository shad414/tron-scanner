@extends('layout')
@section('title', 'چاودێری Telegram')

@section('content')
    <h1 class="text-2xl font-extrabold mb-1">🔔 چاودێری ئادرەسەکان</h1>
    <p class="muted text-sm mb-5">هەر کاتێک پارە بێت یان بڕوات، لە Telegram ئاگادار دەکرێیتەوە.</p>

    @unless ($telegram)
        <div class="card p-4 mb-4 text-sm leading-7" style="border-color: rgba(251,191,36,.4)">
            <b class="c-warn">Telegram هێشتا ڕێک نەخراوە.</b><br>
            ١. لە Telegram بڕۆ بۆ <span class="mono">@BotFather</span>، فەرمانی <span class="mono">/newbot</span> بنێرە و token ەکە کۆپی بکە.<br>
            ٢. لە فایلی <span class="mono">.env</span> دایبنێ: <span class="mono">TELEGRAM_BOT_TOKEN=…</span><br>
            ٣. <span class="mono">/start</span> بۆ بۆتەکەت بنێرە، و Chat ID ی خۆت لە <span class="mono">@userinfobot</span> وەربگرە.<br>
            ٤. با <span class="mono">php artisan schedule:work</span> کار بکات (هەر خولەکێک دەپشکنێت).
        </div>
    @endunless

    <details class="card sec" {{ $watches->isEmpty() || old('address') ? 'open' : '' }}>
        <summary><span class="sec-icon">＋</span>زیادکردنی چاودێری<span class="chev">⌄</span></summary>
        <form method="post" action="{{ route('watches.store') }}" class="body space-y-3">
            @csrf
            <input name="address" required placeholder="ئادرەسی TRON (T…)" value="{{ old('address') }}" class="field mono" spellcheck="false" autocapitalize="off">
            <input name="chat_id" required inputmode="numeric" placeholder="Telegram Chat ID" value="{{ old('chat_id') }}" class="field mono">
            <input name="label" maxlength="100" placeholder="ناو (ئارەزوومەندانە)" value="{{ old('label') }}" class="field">
            <button class="btn btn-primary w-full">چاودێری بکە</button>
        </form>
    </details>

    <details class="card sec">
        <summary><span class="sec-icon">🧪</span>تاقیکردنەوەی Telegram<span class="chev">⌄</span></summary>
        <form method="post" action="{{ route('watches.test') }}" class="body space-y-3">
            @csrf
            <p class="text-sm muted">نامەیەکی تاقیکردنەوە بۆ ئەم Chat ID یە دەنێرێت.</p>
            <input name="chat_id" required inputmode="numeric" placeholder="Telegram Chat ID" class="field mono">
            <button class="btn w-full" @disabled(! $telegram)>ناردنی نامەی تاقیکردنەوە</button>
        </form>
    </details>

    <h2 class="font-bold mt-6 mb-3">چاودێرییە چالاکەکان <span class="pill pill-mute">{{ $watches->count() }}</span></h2>
    <div class="space-y-2.5">
        @forelse ($watches as $w)
            <div class="card p-3.5">
                <div class="flex items-start gap-3">
                    <a href="{{ route('scan', ['address' => $w->address]) }}" class="min-w-0 flex-1">
                        <span class="font-bold block">{{ $w->label ?? 'بێ ناو' }}</span>
                        <span class="mono block text-[12.5px] c-blue truncate text-left mt-0.5">{{ $w->address }}</span>
                    </a>
                    @if ($w->was_blacklisted)
                        <span class="pill pill-bad">⛔ بلاک‌لیست</span>
                    @else
                        <span class="pill pill-ok">پاک</span>
                    @endif
                </div>
                <div class="flex items-center justify-between gap-2 mt-3 pt-3 text-[12px] faint" style="border-top: 1px solid var(--line)">
                    <span>Chat <span class="mono">{{ $w->chat_id }}</span> · دوایین جووڵە: <span class="mono">{{ $w->last_seen_ms ? date('Y-m-d H:i', intdiv($w->last_seen_ms, 1000)) : '—' }}</span></span>
                    <form method="post" action="{{ route('watches.destroy', $w) }}" onsubmit="return confirm('لاببرێت؟')">
                        @csrf @method('delete')
                        <button class="btn btn-danger !py-1 !px-3 !text-[12px]">لابردن</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="card p-8 text-center muted">هیچ ئادرەسێک چاودێری ناکرێت.</div>
        @endforelse
    </div>
@endsection
