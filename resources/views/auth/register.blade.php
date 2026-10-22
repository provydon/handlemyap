@extends('layout')
@section('title', 'Create account')
@section('content')
<div class="min-h-[70vh] flex items-center justify-center">
    <div class="w-full max-w-sm">
        <div class="text-center mb-6">
            <div class="text-2xl font-semibold text-slate-900">Handlemy<span class="text-indigo-600">AP</span></div>
            <p class="text-sm text-slate-500 mt-1">AI reads your invoices and pays them via PayPal.</p>
        </div>
        <form method="POST" action="/register" class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-4">
            @csrf
            @foreach ($errors->all() as $error)
                <p class="text-sm text-rose-600">{{ $error }}</p>
            @endforeach
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
                <input name="name" value="{{ old('name') }}" required autofocus
                       class="w-full rounded-lg border-slate-300 border px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                <input name="email" type="email" value="{{ old('email') }}" required
                       class="w-full rounded-lg border-slate-300 border px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                <input name="password" type="password" required
                       class="w-full rounded-lg border-slate-300 border px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Confirm password</label>
                <input name="password_confirmation" type="password" required
                       class="w-full rounded-lg border-slate-300 border px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
            <button class="w-full bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-3 py-2 font-medium">Create account</button>
            <p class="text-xs text-slate-500 text-center">Already have an account? <a href="/login" class="text-indigo-600 hover:underline">Sign in</a></p>
        </form>
    </div>
</div>
@endsection
