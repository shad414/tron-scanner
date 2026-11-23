@extends('layout')
@section('title', 'مێژووی سکان')

@section('content')
    @php $badge = [['✓ پاک', 'ok'], ['! ئاگاداربە', 'warn'], ['✕ پیس', 'bad']]; @endphp
    <h1 class="text-2xl font-extrabold mb-4">مێژووی سکان</h1>

    <form method="get" class="card p-2 mb-4 flex gap-2">
        <input name="q" value="{{ $q }}" placeholder="گەڕان بە ئادرەس…" class="field mono !border-0 !bg-transparent !shadow-none flex-1" spellcheck="false" autocapitalize="off" enterkeyhint="search">
        <button class="btn btn-primary !px-5">گەڕان</button>
    </form>

    <div class="space-y-2.5">
        @forelse ($scans as $s)
            @php [$label, $tone] = $badge[$s->risk_level]; @endphp
            <a href="{{ route('history.show', $s) }}" class="card flex items-center gap-3 p-3.5">
                <span class="shrink-0 w-11 h-11 rounded-xl grid place-items-center font-extrabold" style="background: var(--{{ $tone }}-bg); color: var(--{{ $tone }})">{{ mb_substr($label, 0, 1) }}</span>
                <span class="min-w-0 flex-1">
                    <span class="mono block text-[13px] truncate text-left">{{ $s->address }}</span>
                    <span class="flex flex-wrap items-center gap-1.5 mt-1">
                        <span class="pill pill-{{ $tone }}">{{ $label }}</span>
                        @if ($s->taint_percent > 0)
                            <span class="pill {{ $s->taint_percent >= 10 ? 'pill-bad' : 'pill-warn' }}">پیس {{ rtrim(rtrim($s->taint_percent, '0'), '.') }}٪</span>
                        @endif
                        @if ($s->exchanges)<span class="pill pill-gold">🏦 {{ \Illuminate\Support\Str::limit($s->exchanges, 24) }}</span>@endif
                    </span>
                </span>
                <span class="text-left shrink-0">
                    <span class="mono block font-bold text-[14px]">{{ number_format($s->usdt_balance, 2) }}</span>
                    <span class="block text-[11px] faint">USDT · #{{ $s->id }}</span>
                    <span class="block text-[11px] faint mono">{{ $s->created_at->format('m-d H:i') }}</span>
                </span>
            </a>
        @empty
            <div class="card p-8 text-center muted">هێشتا هیچ سکانێک نەکراوە.</div>
        @endforelse
    </div>
    <div class="mt-5">{{ $scans->links() }}</div>
@endsection
