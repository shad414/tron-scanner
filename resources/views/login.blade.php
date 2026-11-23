@extends('layout')
@section('title', 'چوونەژوورەوە')

@section('content')
    <form method="post" action="{{ url('/login') }}" class="max-w-sm mx-auto card p-6 space-y-4 mt-8">
        @csrf
        <div class="text-center">
            <div class="logo-mark mx-auto !w-14 !h-14 !text-2xl !rounded-2xl mb-3">🔒</div>
            <h1 class="text-xl font-extrabold">{{ $admin ? 'چوونەژوورەوەی بەڕێوەبەر' : 'چوونەژوورەوە' }}</h1>
            @if ($admin)
                <p class="text-sm muted mt-1">مێژووی سکان و چاودێری Telegram تەنها بۆ بەڕێوەبەرە.</p>
            @endif
        </div>
        <input type="password" name="password" required autofocus placeholder="وشەی نهێنی" class="field" autocomplete="current-password">
        <button class="btn btn-primary w-full !py-3.5">چوونەژوورەوە</button>
    </form>
@endsection
