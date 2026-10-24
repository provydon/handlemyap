@extends('layout')
@section('title', 'Settings')
@section('content')

<h1 class="text-xl font-semibold text-slate-900 mb-5">Settings</h1>

{{-- Intake link --}}
<div class="bg-white rounded-2xl border border-slate-200 p-5 mb-6">
    <div class="font-medium text-slate-800 mb-1">Your invoice intake link</div>
    <p class="text-xs text-slate-500 mb-3">Share this and vendors send invoices straight into your queue — no more chasing your email or WhatsApp.</p>
    <div class="flex items-center gap-2 mb-4">
        <input id="intake-url" readonly aria-label="Your intake link" value="{{ url('/u/'.auth()->user()->handle) }}"
               class="flex-1 rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-600">
        <a href="{{ url('/u/'.auth()->user()->handle) }}" target="_blank"
           class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Open</a>
    </div>
    <form method="POST" action="/settings/handle" class="flex items-center gap-2">
        @csrf
        <span class="text-sm text-slate-400">{{ url('/u') }}/</span>
        <input id="handle" name="handle" value="{{ auth()->user()->handle }}" aria-label="Intake handle" autocomplete="off"
               class="w-48 rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <button class="bg-slate-800 hover:bg-slate-900 text-white rounded-full px-4 py-2 text-sm font-medium">Save link</button>
    </form>
</div>

<div class="grid md:grid-cols-2 gap-6">
    {{-- Auto-approval rules --}}
    <form method="POST" action="/settings/rules" class="bg-white rounded-2xl border border-slate-200 p-5">
        @csrf
        <div class="font-medium text-slate-800 mb-1">Auto-approval</div>
        <p class="text-xs text-slate-500 mb-4">Which invoices pay themselves — no human tap. Everything else waits in your queue.</p>

        <label class="flex items-center gap-2 mb-3 text-sm">
            <input id="enabled" type="checkbox" name="enabled" value="1" @checked($rule?->enabled) class="rounded">
            <span>Enable auto-approval</span>
        </label>

        <label for="max_amount" class="block text-sm text-slate-700 mb-1">Only auto-pay at or below</label>
        <div class="flex items-center gap-2 mb-3">
            <span class="text-slate-400">$</span>
            <input id="max_amount" name="max_amount" type="number" step="0.01" value="{{ $rule?->max_amount }}" placeholder="500.00"
                   class="w-40 rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>

        <label for="daily_cap" class="block text-sm text-slate-700 mb-1">Daily auto-pay cap (optional)</label>
        <div class="flex items-center gap-2 mb-4">
            <span class="text-slate-400">$</span>
            <input id="daily_cap" name="daily_cap" type="number" step="0.01" value="{{ $rule?->daily_cap }}" placeholder="2000.00"
                   class="w-40 rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>

        <label class="flex items-center gap-2 mb-2 text-sm">
            <input id="known_vendors_only" type="checkbox" name="known_vendors_only" value="1" @checked($rule?->known_vendors_only ?? true) class="rounded">
            <span>Known vendors only (new vendors always need review)</span>
        </label>
        <label class="flex items-center gap-2 mb-4 text-sm">
            <input id="block_duplicates" type="checkbox" name="block_duplicates" value="1" @checked($rule?->block_duplicates ?? true) class="rounded">
            <span>Hold possible duplicates</span>
        </label>

        <button class="bg-brand-600 hover:bg-brand-700 text-white rounded-full px-4 py-2 text-sm font-medium">Save rules</button>
    </form>

    {{-- API tokens --}}
    <div class="bg-white rounded-2xl border border-slate-200 p-5">
        <div class="font-medium text-slate-800 mb-1">API tokens</div>
        <p class="text-xs text-slate-500 mb-4">For integrators (NotaryDash, your apps) to submit invoices to <code>/api/v1</code>.</p>

        @if ($plainToken)
            <div class="rounded-lg bg-brand-50 border border-brand-200 p-3 mb-4">
                <div class="text-xs text-brand-700 mb-1">Copy this now — it won't be shown again:</div>
                <code class="text-xs break-all text-brand-900">{{ $plainToken }}</code>
            </div>
        @endif

        <form method="POST" action="/settings/tokens" class="flex gap-2 mb-4">
            @csrf
            <input id="token-name" name="name" placeholder="e.g. NotaryDash" required aria-label="Token name" autocomplete="off" class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <button class="bg-slate-800 hover:bg-slate-900 text-white rounded-full px-4 py-2 text-sm font-medium">Create</button>
        </form>

        <ul class="divide-y divide-slate-100 text-sm">
            @forelse ($tokens as $token)
                <li class="flex items-center justify-between py-2">
                    <span class="text-slate-700">{{ $token->name }}</span>
                    <form method="POST" action="/settings/tokens/{{ $token->id }}">
                        @csrf @method('DELETE')
                        <button class="text-rose-500 hover:text-rose-700 text-xs">Revoke</button>
                    </form>
                </li>
            @empty
                <li class="py-2 text-slate-400">No tokens yet.</li>
            @endforelse
        </ul>
    </div>
</div>
@endsection
