@extends('layout')
@section('title', 'Vendors')
@section('content')

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900">Vendors</h1>
    <p class="text-sm text-slate-500 max-w-xl">Add each vendor's PayPal email so a payment lands with them. It's used as the recipient when you pay an invoice with PayPal.</p>
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
                        @if ($r['connected'])
                            PayPal: {{ $r['paypal_email'] }}
                        @else
                            {{ $vendor->email ?? 'No email on file' }}
                        @endif
                    </div>
                </div>
                @if ($r['connected'])
                    <button type="button" onclick="toggleBankForm('{{ $vendor->id }}')"
                            class="shrink-0 inline-flex items-center gap-1 text-xs font-medium text-emerald-700 bg-emerald-50 rounded-full px-3 py-1 hover:bg-emerald-100">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                        PayPal on file
                    </button>
                @else
                    <button type="button" onclick="toggleBankForm('{{ $vendor->id }}')"
                            class="shrink-0 rounded-full border border-slate-300 px-3.5 py-1.5 text-xs text-slate-700 hover:bg-slate-50">Add PayPal</button>
                @endif
            </div>

            <form id="bank-form-{{ $vendor->id }}" method="POST" action="/app/vendors/{{ $vendor->id }}/bank" class="hidden mt-3">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-2 items-end">
                    <div class="sm:col-span-3">
                        <label class="block text-[11px] text-slate-500 mb-1">PayPal email</label>
                        <input name="paypal_email" type="email" required placeholder="vendor@example.com" value="{{ $r['paypal_email'] ?? $vendor->email }}" class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm">
                    </div>
                    <button class="bg-brand-600 hover:bg-brand-700 text-white rounded-full px-4 py-1.5 text-sm font-medium">Save PayPal</button>
                </div>
            </form>
        </div>
    @empty
        <div class="p-6 text-sm text-slate-400">No vendors yet — they're created automatically from the invoices you receive.</div>
    @endforelse
</div>

<script>
    function toggleBankForm(id) {
        document.getElementById('bank-form-' + id)?.classList.toggle('hidden');
    }
</script>
@endsection
