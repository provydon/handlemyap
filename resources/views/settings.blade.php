@extends('layout')
@section('title', 'Settings')
@section('content')

<h1 class="text-xl font-semibold text-slate-900 mb-5">Settings</h1>

<div class="grid md:grid-cols-2 gap-6">
    {{-- Auto-approval rules --}}
    <form method="POST" action="/settings/rules" class="bg-white rounded-xl border border-slate-200 p-5">
        @csrf
        <div class="font-medium text-slate-800 mb-1">Auto-approval</div>
        <p class="text-xs text-slate-500 mb-4">Which invoices pay themselves — no human tap. Everything else waits in your queue.</p>

        <label class="flex items-center gap-2 mb-3 text-sm">
            <input type="checkbox" name="enabled" value="1" @checked($rule?->enabled) class="rounded">
            <span>Enable auto-approval</span>
        </label>

        <label class="block text-sm text-slate-700 mb-1">Only auto-pay at or below</label>
        <div class="flex items-center gap-2 mb-3">
            <span class="text-slate-400">$</span>
            <input name="max_amount" type="number" step="0.01" value="{{ $rule?->max_amount }}" placeholder="500.00"
                   class="w-40 rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>

        <label class="block text-sm text-slate-700 mb-1">Daily auto-pay cap (optional)</label>
        <div class="flex items-center gap-2 mb-4">
            <span class="text-slate-400">$</span>
            <input name="daily_cap" type="number" step="0.01" value="{{ $rule?->daily_cap }}" placeholder="2000.00"
                   class="w-40 rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>

        <label class="flex items-center gap-2 mb-2 text-sm">
            <input type="checkbox" name="known_vendors_only" value="1" @checked($rule?->known_vendors_only ?? true) class="rounded">
            <span>Known vendors only (new vendors always need review)</span>
        </label>
        <label class="flex items-center gap-2 mb-4 text-sm">
            <input type="checkbox" name="block_duplicates" value="1" @checked($rule?->block_duplicates ?? true) class="rounded">
            <span>Hold possible duplicates</span>
        </label>

        <button class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-4 py-2 text-sm font-medium">Save rules</button>
    </form>

    {{-- API tokens --}}
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <div class="font-medium text-slate-800 mb-1">API tokens</div>
        <p class="text-xs text-slate-500 mb-4">For integrators (NotaryDash, your apps) to submit invoices to <code>/api/v1</code>.</p>

        @if ($plainToken)
            <div class="rounded-lg bg-indigo-50 border border-indigo-200 p-3 mb-4">
                <div class="text-xs text-indigo-700 mb-1">Copy this now — it won't be shown again:</div>
                <code class="text-xs break-all text-indigo-900">{{ $plainToken }}</code>
            </div>
        @endif

        <form method="POST" action="/settings/tokens" class="flex gap-2 mb-4">
            @csrf
            <input name="name" placeholder="e.g. NotaryDash" required class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <button class="bg-slate-800 hover:bg-slate-900 text-white rounded-lg px-4 py-2 text-sm font-medium">Create</button>
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
