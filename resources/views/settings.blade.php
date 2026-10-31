@extends('layout')
@section('title', 'Settings')
@section('content')

@php($u = auth()->user())
@php($tabs = ['intake' => 'Intake link', 'bank' => 'Bank (ACH)', 'currency' => 'Currency', 'rules' => 'Auto-approval', 'tokens' => 'API tokens'])
{{-- Active tab: ?tab= query param, else the Bank tab if a connect attempt errored, else the first. --}}
@php($active = request('tab'))
@php($active = array_key_exists($active, $tabs) ? $active : ($errors->has('routing_number') ? 'bank' : 'intake'))

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

{{-- Bank for ACH payouts (via Dwolla) — vendors get paid by bank transfer --}}
<div data-panel="bank" class="{{ $active === 'bank' ? '' : 'hidden' }}">
    <div class="bg-white rounded-2xl border border-slate-200 p-5 max-w-2xl">
        <div class="flex items-center justify-between mb-1">
            <div class="font-medium text-slate-800">Bank for ACH payouts</div>
            <span id="bk-badge" class="text-[11px] font-semibold uppercase tracking-wide rounded px-2 py-0.5 text-slate-500 bg-slate-100 {{ $u->dwollaConnected() ? '' : 'hidden' }}">ACH · Dwolla</span>
        </div>

        {{-- Connected view --}}
        <div id="bk-connected" class="{{ $u->dwollaConnected() ? '' : 'hidden' }}">
            <p class="text-xs text-slate-500 mb-4">Approved invoices are paid by bank transfer (ACH) from this account.</p>
            <div class="flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                <div class="min-w-0">
                    <div class="text-sm font-medium text-emerald-900 flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                        Connected<span id="bk-name">{{ $u->dwolla_bank_name ? ' — '.$u->dwolla_bank_name : '' }}</span>
                    </div>
                    <div class="text-xs text-emerald-700 mt-0.5 truncate">Bank funding source linked for payouts</div>
                </div>
                <form id="bk-disconnect-form" method="POST" action="/settings/bank/disconnect">
                    @csrf
                    <button class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Disconnect</button>
                </form>
            </div>
        </div>

        {{-- Connect view --}}
        <div id="bk-disconnected" class="{{ $u->dwollaConnected() ? 'hidden' : '' }}">
            <p class="text-sm text-slate-600 mb-4">
                Connect the bank account approved invoices are paid <strong>from</strong>. The fastest way is
                instant verification — log in to your bank and you're ready to send right away.
            </p>

            {{-- PRIMARY: Plaid instant verification → a Dwolla-verified source that can send. --}}
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 mb-4">
                <div class="flex items-center gap-3">
                    <button type="button" id="bk-plaid-btn"
                            class="rounded-full bg-brand-600 hover:bg-brand-700 text-white px-5 py-2 text-sm font-medium disabled:opacity-60">
                        Connect bank instantly
                    </button>
                    <span id="bk-plaid-feedback" class="text-xs text-slate-500"></span>
                </div>
                <p class="text-[11px] text-slate-400 mt-2">Verifies instantly via Plaid, so you can pay out right away — no micro-deposit wait.</p>
            </div>

            {{-- FALLBACK: manual routing/account. Creates the funding source but it
                 must still be verified (micro-deposits) before it can SEND. --}}
            <button type="button" onclick="document.getElementById('bk-manual').classList.toggle('hidden')"
                    class="text-xs font-medium text-brand-600 hover:text-brand-700 mb-3">Or enter bank details manually →</button>

            <form id="bk-manual" method="POST" action="/settings/bank/connect" class="hidden space-y-3">
                @csrf
                <div>
                    <label for="bank_name" class="block text-sm text-slate-700 mb-1">Account name</label>
                    <input id="bank_name" name="bank_name" required autocomplete="off" placeholder="Acme Inc — Operating"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm @error('bank_name') border-rose-400 @enderror">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="routing_number" class="block text-sm text-slate-700 mb-1">Routing number</label>
                        <input id="routing_number" name="routing_number" required inputmode="numeric" autocomplete="off" placeholder="222222226"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm @error('routing_number') border-rose-400 @enderror">
                        @error('routing_number')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="account_number" class="block text-sm text-slate-700 mb-1">Account number</label>
                        <input id="account_number" name="account_number" required inputmode="numeric" autocomplete="off" placeholder="1234567890"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm @error('account_number') border-rose-400 @enderror">
                    </div>
                </div>
                <div class="flex items-end gap-2">
                    <div>
                        <label for="account_type" class="block text-sm text-slate-700 mb-1">Account type</label>
                        <select id="account_type" name="account_type" class="hma-select rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                            <option value="checking">Checking</option>
                            <option value="savings">Savings</option>
                        </select>
                    </div>
                    <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white rounded-full px-5 py-2 text-sm font-medium disabled:opacity-60">Connect bank</button>
                    <span id="bk-connect-feedback" class="text-xs text-slate-500 self-center"></span>
                </div>
                <p class="text-[11px] text-slate-400">Bank details go to Dwolla to create a funding source — we store only its id, never your account number. A manually-added source needs micro-deposit verification before it can send.</p>
            </form>
        </div>
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

{{-- Auto-approval rules --}}
<div data-panel="rules" class="{{ $active === 'rules' ? '' : 'hidden' }}">
    <form method="POST" action="/settings/rules" class="bg-white rounded-2xl border border-slate-200 p-5 max-w-2xl">
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

{{-- Plaid Link (instant bank verification) — CDN, no build step. --}}
<script src="https://cdn.plaid.com/link/v2/stable/link-initialize.js"></script>
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

// --- Bank (ACH) connect / disconnect via API, no page reload ---
(function () {
    const connected = document.getElementById('bk-connected');
    const disconnected = document.getElementById('bk-disconnected');
    const badge = document.getElementById('bk-badge');
    const connectForm = document.getElementById('bk-manual');
    const disconnectForm = document.getElementById('bk-disconnect-form');
    const feedback = document.getElementById('bk-connect-feedback');
    const say = (msg, err) => { if (feedback) { feedback.textContent = msg; feedback.className = 'text-xs self-center ' + (err ? 'text-rose-600' : 'text-slate-500'); } };

    function showConnected(d) {
        document.getElementById('bk-name').textContent = d.bank_name ? ' — ' + d.bank_name : '';
        badge.classList.remove('hidden');
        disconnected.classList.add('hidden');
        connected.classList.remove('hidden');
    }
    function showDisconnected() {
        connected.classList.add('hidden');
        disconnected.classList.remove('hidden');
        badge.classList.add('hidden');
        connectForm && connectForm.reset();
        say('');
    }

    // --- PRIMARY: Plaid instant verification ---
    const plaidBtn = document.getElementById('bk-plaid-btn');
    const plaidFeedback = document.getElementById('bk-plaid-feedback');
    const plaidSay = (msg, err) => { if (plaidFeedback) { plaidFeedback.textContent = msg; plaidFeedback.className = 'text-xs ' + (err ? 'text-rose-600' : 'text-slate-500'); } };

    plaidBtn && plaidBtn.addEventListener('click', async () => {
        if (typeof Plaid === 'undefined') { plaidSay('Bank connector failed to load — use manual entry below.', true); return; }
        plaidBtn.disabled = true;
        plaidSay('Starting…');
        try {
            const tokRes = await fetch('/settings/bank/plaid/link-token', {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
            });
            if (!tokRes.ok) { plaidSay((await tokRes.json()).message || 'Could not start.', true); plaidBtn.disabled = false; return; }
            const { link_token } = await tokRes.json();

            const handler = Plaid.create({
                token: link_token,
                onSuccess: async (public_token, metadata) => {
                    plaidSay('Verifying…');
                    const acct = (metadata.accounts && metadata.accounts[0]) || {};
                    const body = new FormData();
                    body.append('public_token', public_token);
                    body.append('account_id', acct.id || '');
                    body.append('bank_name', (metadata.institution && metadata.institution.name) || acct.name || 'Bank account');
                    body.append('_token', document.querySelector('meta[name=csrf-token]').content);
                    const res = await fetch('/settings/bank/plaid/connect', {
                        method: 'POST', headers: { 'Accept': 'application/json' }, body,
                    });
                    if (res.ok) { showConnected(await res.json()); }
                    else { const j = await res.json().catch(() => ({})); plaidSay(j.message || 'Could not connect.', true); plaidBtn.disabled = false; }
                },
                onExit: () => { plaidBtn.disabled = false; plaidSay(''); },
            });
            handler.open();
        } catch (err) {
            plaidSay('Network error — please retry.', true);
            plaidBtn.disabled = false;
        }
    });

    connectForm && connectForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = connectForm.querySelector('button[type=submit]');
        btn.disabled = true;
        say('Connecting…');
        try {
            const res = await fetch(connectForm.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: new FormData(connectForm),
            });
            if (res.ok) {
                showConnected(await res.json());
            } else if (res.status === 422) {
                const j = await res.json();
                const firstErr = j.errors ? Object.values(j.errors)[0] : null;
                say((firstErr && firstErr[0]) || j.message || 'Could not connect.', true);
            } else {
                say('Something went wrong (' + res.status + ').', true);
            }
        } catch (err) {
            say('Network error — please retry.', true);
        } finally {
            btn.disabled = false;
        }
    });

    disconnectForm && disconnectForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = disconnectForm.querySelector('button');
        btn.disabled = true;
        try {
            const res = await fetch(disconnectForm.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: new FormData(disconnectForm),
            });
            if (res.ok) showDisconnected();
        } finally {
            btn.disabled = false;
        }
    });
})();
</script>
@endsection
