@extends('layout')
@section('title', 'Vendors')
@section('content')

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900">Vendors</h1>
    <p class="text-sm text-slate-500 max-w-xl">Attach each vendor's bank so approved invoices can be paid. The payment method is chosen automatically from the vendor's country — ACH for the US, Paystack for Nigeria, and so on.</p>
</div>

@if ($errors->any())
    <div class="mb-4 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 px-4 py-2 text-sm max-w-3xl">
        {{ $errors->first() }}
    </div>
@endif

<div class="bg-white rounded-2xl border border-slate-200 divide-y divide-slate-100 max-w-3xl">
    @forelse ($vendors as $vendor)
        @php($r = $routing[$vendor->id])
        <div class="p-4">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="font-medium text-slate-800 truncate">{{ $vendor->name }}</div>
                    <div class="text-xs text-slate-500 truncate">
                        {{ $vendor->email ?? 'No email on file' }}
                        @if ($r['country'])
                            <span class="text-slate-300">·</span> {{ $r['country'] }}
                            @if ($r['provider']) <span class="text-slate-400">via {{ $r['provider'] }}</span> @endif
                        @endif
                    </div>
                </div>
                @if ($r['connected'])
                    <span class="shrink-0 inline-flex items-center gap-1 text-xs font-medium text-emerald-700 bg-emerald-50 rounded-full px-3 py-1">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                        Bank on file
                    </span>
                @elseif ($r['usable'])
                    <button type="button" onclick="toggleBankForm('{{ $vendor->id }}')"
                            class="shrink-0 rounded-full border border-slate-300 px-3.5 py-1.5 text-xs text-slate-700 hover:bg-slate-50">Add bank</button>
                @else
                    <span class="shrink-0 text-xs text-slate-400">{{ $r['country'] ? 'No rail for '.$r['country'].' yet' : 'Set a country to pay' }}</span>
                @endif
            </div>

            @if (! $r['connected'] && $r['usable'])
                <form id="bank-form-{{ $vendor->id }}" method="POST" action="/app/vendors/{{ $vendor->id }}/bank" class="hidden mt-3">
                    @csrf
                    @if ($r['rail'] === 'paystack')
                        {{-- Nigeria · Paystack transfer: bank + NUBAN account number --}}
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-2 items-end">
                            <div class="sm:col-span-2">
                                <label class="block text-[11px] text-slate-500 mb-1">Bank</label>
                                @if (! empty($banks))
                                    <select name="bank_code" required class="hma-select w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-sm">
                                        <option value="">Select bank…</option>
                                        @foreach ($banks as $bank)
                                            <option value="{{ $bank['code'] }}">{{ $bank['name'] }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input name="bank_code" required inputmode="numeric" placeholder="Bank code (e.g. 058)" class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm">
                                @endif
                            </div>
                            <div class="sm:col-span-1">
                                <label class="block text-[11px] text-slate-500 mb-1">Account number</label>
                                <input name="account_number" required inputmode="numeric" placeholder="0123456789" class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm">
                            </div>
                            <button class="bg-brand-600 hover:bg-brand-700 text-white rounded-full px-4 py-1.5 text-sm font-medium">Save bank</button>
                        </div>
                    @elseif (in_array($r['rail'], ['wise', 'stripe']))
                        {{-- UK · Wise or Stripe Global Payouts: sort code + account number --}}
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-2 items-end">
                            <div class="sm:col-span-1">
                                <label class="block text-[11px] text-slate-500 mb-1">Sort code</label>
                                <input name="sort_code" required inputmode="numeric" placeholder="40-30-20" class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-[11px] text-slate-500 mb-1">Account number</label>
                                <input name="account_number" required inputmode="numeric" placeholder="12345678" class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm">
                            </div>
                            <button class="bg-brand-600 hover:bg-brand-700 text-white rounded-full px-4 py-1.5 text-sm font-medium">Save bank</button>
                        </div>
                    @else
                        {{-- US · ACH via Dwolla: routing + account + type --}}
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-2 items-end">
                            <div class="sm:col-span-1">
                                <label class="block text-[11px] text-slate-500 mb-1">Routing</label>
                                <input name="routing_number" required inputmode="numeric" placeholder="222222226" class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm">
                            </div>
                            <div class="sm:col-span-1">
                                <label class="block text-[11px] text-slate-500 mb-1">Account</label>
                                <input name="account_number" required inputmode="numeric" placeholder="1234567890" class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm">
                            </div>
                            <div class="sm:col-span-1">
                                <label class="block text-[11px] text-slate-500 mb-1">Type</label>
                                <select name="account_type" class="hma-select w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-sm">
                                    <option value="checking">Checking</option>
                                    <option value="savings">Savings</option>
                                </select>
                            </div>
                            <button class="bg-brand-600 hover:bg-brand-700 text-white rounded-full px-4 py-1.5 text-sm font-medium">Save bank</button>
                        </div>
                    @endif
                </form>
            @endif
        </div>
    @empty
        <div class="p-6 text-sm text-slate-400">No vendors yet — they're created automatically from the invoices you receive.</div>
    @endforelse
</div>

<style>
    .hma-select {
        -webkit-appearance: none; appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='2' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='m19.5 8.25-7.5 7.5-7.5-7.5'/%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 0.6rem center; background-size: 0.9rem; padding-right: 2rem;
    }
</style>
<script>
    function toggleBankForm(id) {
        document.getElementById('bank-form-' + id)?.classList.toggle('hidden');
    }
</script>
@endsection
