@extends('layout')
@section('title', 'Sign in')
@section('content')
<div class="min-h-[70vh] flex items-center justify-center">
    <div class="w-full max-w-sm">
        <div class="text-center mb-6">
            <div class="text-2xl font-semibold text-slate-900">handle<span class="text-indigo-600">my</span>ap</div>
            <p class="text-sm text-slate-500 mt-1">AI reads your invoices and pays them via PayPal.</p>
        </div>
        <form method="POST" action="/login" class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-4">
            @csrf
            @error('email')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                <input name="email" type="email" value="{{ old('email') }}" required autofocus
                       class="w-full rounded-lg border-slate-300 border px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                <input name="password" type="password" required
                       class="w-full rounded-lg border-slate-300 border px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
            <button class="w-full bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-3 py-2 font-medium">Sign in</button>
            <p class="text-xs text-slate-400 text-center">Demo: demo@handlemyap.test / password</p>
        </form>
    </div>
</div>
@endsection
