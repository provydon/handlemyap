@extends('layout')
@section('title', 'Settings')
@section('content')

@php($u = auth()->user())
@php($tabs = ['intake' => 'Intake link', 'currency' => 'Currency', 'tokens' => 'API tokens'])
{{-- Active tab: ?tab= query param, else the first. --}}
@php($active = request('tab'))
@php($active = array_key_exists($active, $tabs) ? $active : 'intake')

<h1 class="text-xl font-semibold text-slate-900 mb-4">Settings</h1>

{{-- Tab bar --}}
<div class="border-b border-slate-200 mb-6 overflow-x-auto">
    <nav class="flex gap-1 min-w-max">
        @foreach ($tabs as $key => $label)
            <button type="button" data-tab="{{ $key }}"
                class="px-4 py-2.5 text-sm border-b-2 -mb-px whitespace-nowrap hover:text-slate-700
                    {{ $active === $key ? 'border-brand-600 text-brand-700 font-medium' : 'border-transparent text-slate-500' }}">
                {{ $label }}
            </button>
        @endforeach
    </nav>
</div>

{{-- Intake link --}}
<div data-panel="intake" class="{{ $active === 'intake' ? '' : 'hidden' }}">
    <div class="bg-white rounded-2xl border border-slate-200 p-5 max-w-2xl">
        <div class="font-medium text-slate-800 mb-1">Your invoice intake link</div>
        <p class="text-xs text-slate-500 mb-3">Share this and vendors send invoices straight into your queue — no more chasing your email or WhatsApp.</p>
        <div class="flex items-center gap-2 mb-4">
            <input id="intake-url" readonly aria-label="Your intake link" value="{{ url('/u/'.$u->handle) }}"
                   class="flex-1 rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-600">
            <a href="{{ url('/u/'.$u->handle) }}" target="_blank"
               class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Open</a>
        </div>
        <form method="POST" action="/settings/handle" class="flex items-center gap-2">
            @csrf
            <span class="text-sm text-slate-400">{{ url('/u') }}/</span>
            <input id="handle" name="handle" value="{{ $u->handle }}" aria-label="Intake handle" autocomplete="off"
                   class="w-48 rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <button class="bg-slate-800 hover:bg-slate-900 text-white rounded-full px-4 py-2 text-sm font-medium">Save link</button>
        </form>
    </div>
</div>

{{-- Default currency --}}
<div data-panel="currency" class="{{ $active === 'currency' ? '' : 'hidden' }}">
    <div class="bg-white rounded-2xl border border-slate-200 p-5 max-w-2xl">
        <div class="font-medium text-slate-800 mb-1">Default currency</div>
        <p class="text-xs text-slate-500 mb-3">Used to pre-fill new invoices and whenever a currency can't be read off a document. Starts at USD.</p>
        @php($currencies = ['USD','EUR','GBP','NGN','CAD','AUD','KES','GHS','ZAR','INR'])
        @php($current = $u->default_currency ?? 'USD')
        <form method="POST" action="/settings/currency" class="flex items-center gap-2">
            @csrf
            <select id="default_currency" name="default_currency" aria-label="Default currency"
                    class="hma-select w-40 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                @foreach ($currencies as $c)
                    <option value="{{ $c }}" @selected($current === $c)>{{ $c }}</option>
                @endforeach
            </select>
            <button class="bg-slate-800 hover:bg-slate-900 text-white rounded-full px-4 py-2 text-sm font-medium">Save currency</button>
        </form>
    </div>
</div>

{{-- API tokens --}}
<div data-panel="tokens" class="{{ $active === 'tokens' ? '' : 'hidden' }}">
    <div class="bg-white rounded-2xl border border-slate-200 p-5 max-w-2xl">
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

<style>
    .hma-select {
        -webkit-appearance: none; appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='2' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='m19.5 8.25-7.5 7.5-7.5-7.5'/%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 0.7rem center; background-size: 0.9rem; padding-right: 2.25rem;
    }
</style>
<script>
(function () {
    const fallback = @json($active);
    function setActive(key) {
        document.querySelectorAll('[data-tab]').forEach(t => {
            const on = t.dataset.tab === key;
            t.classList.toggle('border-brand-600', on);
            t.classList.toggle('text-brand-700', on);
            t.classList.toggle('font-medium', on);
            t.classList.toggle('border-transparent', !on);
            t.classList.toggle('text-slate-500', !on);
        });
        document.querySelectorAll('[data-panel]').forEach(p => {
            p.classList.toggle('hidden', p.dataset.panel !== key);
        });
    }
    document.querySelectorAll('[data-tab]').forEach(t => {
        t.addEventListener('click', () => {
            const key = t.dataset.tab;
            setActive(key);
            const url = new URL(location);
            url.searchParams.set('tab', key);
            history.pushState({}, '', url);
        });
    });
    // Back/forward through tab history.
    window.addEventListener('popstate', () => {
        const key = new URLSearchParams(location.search).get('tab');
        setActive(document.querySelector(`[data-panel="${key}"]`) ? key : fallback);
    });
})();
</script>
@endsection
