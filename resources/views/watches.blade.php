@extends('layout')
@section('title', 'چاودێری Telegram')

@section('content')
    <h1 class="text-2xl font-extrabold mb-5">🔔 چاودێری ئادرەسەکان (Telegram)</h1>

    @unless ($telegram)
        <div class="rounded-xl bg-amber-50 border border-amber-300 text-amber-900 p-4 mb-5 text-sm leading-7">
            <b>Telegram هێشتا ڕێک نەخراوە.</b><br>
            ١. لە Telegram بڕۆ بۆ <span class="mono">@BotFather</span>، فەرمانی <span class="mono">/newbot</span> بنێرە و token ەکە کۆپی بکە.<br>
            ٢. لە فایلی <span class="mono">.env</span> دایبنێ: <span class="mono">TELEGRAM_BOT_TOKEN=…</span><br>
            ٣. <span class="mono">/start</span> بۆ بۆتەکەت بنێرە، و Chat ID ی خۆت لە <span class="mono">@userinfobot</span> وەربگرە.<br>
            ٤. با <span class="mono">php artisan schedule:work</span> کار بکات (هەر خولەکێک دەپشکنێت).
        </div>
    @endunless

    <div class="grid md:grid-cols-2 gap-4 mb-5">
        <form method="post" action="{{ route('watches.store') }}" class="bg-white rounded-2xl shadow-sm p-5 space-y-3">
            @csrf
            <h2 class="font-bold">زیادکردنی چاودێری</h2>
            <input name="address" required placeholder="ئادرەسی TRON (T…)" value="{{ old('address') }}" class="mono w-full rounded-lg border px-3 py-2">
            <input name="chat_id" required inputmode="numeric" placeholder="Telegram Chat ID" value="{{ old('chat_id') }}" class="mono w-full rounded-lg border px-3 py-2">
            <input name="label" maxlength="100" placeholder="ناو (ئارەزوومەندانە)" value="{{ old('label') }}" class="w-full rounded-lg border px-3 py-2">
            <button class="w-full rounded-lg bg-red-600 hover:bg-red-700 text-white py-2 font-semibold">چاودێری بکە</button>
        </form>

        <form method="post" action="{{ route('watches.test') }}" class="bg-white rounded-2xl shadow-sm p-5 space-y-3">
            @csrf
            <h2 class="font-bold">تاقیکردنەوەی Telegram</h2>
            <p class="text-sm text-slate-500">نامەیەکی تاقیکردنەوە بۆ ئەم Chat ID یە دەنێرێت.</p>
            <input name="chat_id" required inputmode="numeric" placeholder="Telegram Chat ID" class="mono w-full rounded-lg border px-3 py-2">
            <button class="w-full rounded-lg bg-slate-800 hover:bg-slate-900 text-white py-2 font-semibold" @disabled(! $telegram)>ناردنی نامەی تاقیکردنەوە</button>
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm whitespace-nowrap">
            <thead class="text-slate-500 border-b"><tr><th class="text-right p-3">ئادرەس</th><th class="text-right">ناو</th><th class="text-right">Chat ID</th><th class="text-right">دوایین جووڵە</th><th class="text-right">بلاک‌لیست</th><th></th></tr></thead>
            <tbody>
            @forelse ($watches as $w)
                <tr class="border-b last:border-0">
                    <td class="p-3"><a href="{{ route('scan', ['address' => $w->address]) }}" class="mono text-blue-700 hover:underline">{{ $w->address }}</a></td>
                    <td>{{ $w->label ?? '—' }}</td>
                    <td class="mono">{{ $w->chat_id }}</td>
                    <td>{{ $w->last_seen_ms ? date('Y-m-d H:i', intdiv($w->last_seen_ms, 1000)) : '—' }}</td>
                    <td>{!! $w->was_blacklisted ? '<span class="text-red-600">⛔ بەڵێ</span>' : '<span class="text-emerald-600">نەخێر</span>' !!}</td>
                    <td class="p-3">
                        <form method="post" action="{{ route('watches.destroy', $w) }}" onsubmit="return confirm('لاببرێت؟')">
                            @csrf @method('delete')
                            <button class="text-red-600 hover:underline">لابردن</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="p-6 text-center text-slate-400">هیچ ئادرەسێک چاودێری ناکرێت.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
