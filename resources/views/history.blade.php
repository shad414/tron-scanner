@extends('layout')
@section('title', 'مێژووی سکان')

@section('content')
    @php $badge = [['✅ پاک', 'bg-emerald-100 text-emerald-800'], ['⚠️ ئاگاداربە', 'bg-amber-100 text-amber-800'], ['⛔ پیس', 'bg-red-100 text-red-800']]; @endphp
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <h1 class="text-2xl font-extrabold">مێژووی سکان</h1>
        <form method="get" class="flex gap-2">
            <input name="q" value="{{ $q }}" placeholder="گەڕان بە ئادرەس…" class="mono rounded-xl border px-3 py-2">
            <button class="rounded-xl bg-slate-800 text-white px-4">گەڕان</button>
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm whitespace-nowrap">
            <thead class="text-slate-500 border-b">
                <tr><th class="text-right p-3">#</th><th class="text-right">کات</th><th class="text-right">ئادرەس</th><th class="text-right">ئەنجام</th><th class="text-right">سەرچاوەی پیس</th><th class="text-right">USDT</th><th class="text-right">ئێکسچەینج</th></tr>
            </thead>
            <tbody>
            @forelse ($scans as $s)
                <tr class="border-b last:border-0 hover:bg-slate-50">
                    <td class="p-3 text-slate-400">{{ $s->id }}</td>
                    <td>{{ $s->created_at->format('Y-m-d H:i') }}</td>
                    <td><a href="{{ route('history.show', $s) }}" class="mono text-blue-700 hover:underline">{{ $s->address }}</a></td>
                    <td><span class="rounded px-2 py-0.5 text-xs {{ $badge[$s->risk_level][1] }}">{{ $badge[$s->risk_level][0] }}</span></td>
                    <td class="mono {{ $s->taint_percent >= 10 ? 'text-red-600 font-bold' : ($s->taint_percent > 0 ? 'text-amber-600' : 'text-slate-400') }}">{{ rtrim(rtrim($s->taint_percent, '0'), '.') }}٪</td>
                    <td class="mono">{{ number_format($s->usdt_balance, 2) }}</td>
                    <td class="text-xs">{{ $s->exchanges ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="p-6 text-center text-slate-400">هێشتا هیچ سکانێک نەکراوە.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $scans->links() }}</div>
@endsection
