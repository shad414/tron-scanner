@extends('layout')
@section('title', 'چوونەژوورەوە')

@section('content')
    <form method="post" action="{{ url('/login') }}" class="max-w-sm mx-auto bg-white rounded-2xl shadow-sm p-6 space-y-4 mt-10">
        @csrf
        <h1 class="text-xl font-extrabold text-center">🔒 چوونەژوورەوە</h1>
        <input type="password" name="password" required autofocus placeholder="وشەی نهێنی" class="w-full rounded-lg border px-3 py-2">
        <button class="w-full rounded-lg bg-red-600 hover:bg-red-700 text-white py-2 font-semibold">چوونەژوورەوە</button>
    </form>
@endsection
