@extends('layout')
@section('title', 'Sign in')
@section('content')
<div class="min-h-[70vh] flex items-center justify-center">
    <div class="w-full max-w-sm">
        <div class="text-center mb-6">
            <a href="/" class="inline-block text-2xl font-semibold text-slate-900">Handlemy<span class="text-brand-600">AP</span></a>
            <p class="text-sm text-slate-500 mt-1">Your vendors send the invoices. AI helps you pay them via PayPal.</p>
        </div>
        <form method="POST" action="/login" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4">
            @csrf
            @error('email')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror
            <div>
                <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                       class="w-full rounded-lg border-slate-300 border px-3 py-2 focus:ring-2 focus:ring-brand-500 focus:outline-none">
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                <div class="relative">
                    <input id="password" name="password" type="password" required autocomplete="current-password"
                           class="w-full rounded-lg border-slate-300 border px-3 py-2 pr-10 focus:ring-2 focus:ring-brand-500 focus:outline-none">
                    <button type="button" onclick="togglePw(this)" tabindex="-1" aria-label="Show password"
                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                        <svg class="w-5 h-5 hidden" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
                    </button>
                </div>
            </div>
            <button class="w-full bg-brand-600 hover:bg-brand-700 text-white rounded-full px-3 py-2 font-medium">Sign in</button>
            <p class="text-xs text-slate-500 text-center">No account? <a href="/register" class="text-brand-600 hover:underline">Create one</a></p>
        </form>
    </div>
</div>
@endsection
