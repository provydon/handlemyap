@extends('layout')
@section('title', 'Invoice '.($invoice->invoice_number ?: '#'.$invoice->id))
@section('content')

@php
    $badges = [
        'needs_review' => ['Needs review', 'bg-amber-100 text-amber-800'],
        'scheduled'    => ['Scheduled', 'bg-sky-100 text-sky-800'],
        'paying'       => ['Paying…', 'bg-sky-100 text-sky-800'],
        'paid'         => ['Paid', 'bg-emerald-100 text-emerald-800'],
        'held'         => ['Held', 'bg-slate-200 text-slate-700'],
        'rejected'     => ['Rejected', 'bg-slate-200 text-slate-500'],
        'failed'       => ['Failed', 'bg-rose-100 text-rose-700'],
        'approved'     => ['Approved', 'bg-emerald-100 text-emerald-800'],
        'received'     => ['Received', 'bg-slate-100 text-slate-600'],
        'extracting'   => ['Reading…', 'bg-slate-100 text-slate-600'],
    ];
    [$badgeText, $badgeClass] = $badges[$invoice->status] ?? [$invoice->status, 'bg-slate-100 text-slate-600'];
    $actionable = in_array($invoice->status, ['needs_review', 'scheduled', 'held'], true);
    $payable = $invoice->amount > 0 && filled($invoice->currency) && $invoice->status !== 'paid';
    $vendorName = $invoice->vendor?->name ?: $invoice->vendor_name ?: 'Unknown vendor';
    $vendorEmail = $invoice->vendor?->email;
@endphp

<div class="mb-5">
    <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-800">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
        Back to Payables
    </a>
</div>

<div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between mb-6">
    <div class="min-w-0">
        <div class="flex items-center gap-3 flex-wrap">
            <h1 class="text-xl font-semibold text-slate-900 truncate">{{ $vendorName }}</h1>
            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $badgeClass }}">{{ $badgeText }}</span>
        </div>
        <p class="text-sm text-slate-500 mt-1">
            Invoice {{ $invoice->invoice_number ?: '#'.$invoice->id }}
            @if($invoice->confidence !== null)· read at {{ $invoice->confidence }}% confidence @endif
        </p>
    </div>
    <div class="shrink-0 text-right">
        <div class="text-2xl font-semibold text-slate-900">{{ $invoice->currency }} {{ $invoice->amount !== null ? number_format((float) $invoice->amount, 2) : '—' }}</div>
        @if($invoice->due_date)<div class="text-xs text-slate-400 mt-0.5">Due {{ $invoice->due_date->toFormattedDateString() }}</div>@endif
    </div>
</div>

{{-- Actions --}}
<div class="flex flex-wrap items-center gap-2 mb-6">
    @if($payable)
        <form method="POST" action="{{ route('invoice.pay.start', $invoice) }}">@csrf
            <button class="inline-flex items-center gap-2 rounded-full bg-brand-600 text-white px-4 py-2 text-sm font-medium hover:bg-brand-700">
                Pay with PayPal
            </button>
        </form>
    @endif
    @if($actionable)
        <form method="POST" action="/app/invoices/{{ $invoice->id }}/hold">@csrf
            <button class="rounded-full border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Hold</button>
        </form>
        <form method="POST" action="/app/invoices/{{ $invoice->id }}/reject"
              onsubmit="return confirm('Reject this invoice?')">@csrf
            <button class="rounded-full border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Reject</button>
        </form>
    @endif
    @if($fileUrl)
        <a href="{{ $fileUrl }}" target="_blank" class="rounded-full border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">View document</a>
    @endif
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    {{-- Details --}}
    <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
        <h2 class="text-sm font-semibold text-slate-700 mb-4">Invoice details</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <div><dt class="text-slate-400">Vendor</dt><dd class="text-slate-800">{{ $vendorName }}</dd></div>
            <div><dt class="text-slate-400">Vendor email</dt><dd class="text-slate-800">{{ $vendorEmail ?: '—' }}</dd></div>
            <div><dt class="text-slate-400">Invoice #</dt><dd class="text-slate-800">{{ $invoice->invoice_number ?: '—' }}</dd></div>
            <div><dt class="text-slate-400">PO reference</dt><dd class="text-slate-800">{{ $invoice->po_ref ?: '—' }}</dd></div>
            <div><dt class="text-slate-400">Amount</dt><dd class="text-slate-800">{{ $invoice->currency }} {{ $invoice->amount !== null ? number_format((float) $invoice->amount, 2) : '—' }}</dd></div>
            <div><dt class="text-slate-400">Issue date</dt><dd class="text-slate-800">{{ $invoice->issue_date?->toFormattedDateString() ?: '—' }}</dd></div>
            <div><dt class="text-slate-400">Due date</dt><dd class="text-slate-800">{{ $invoice->due_date?->toFormattedDateString() ?: '—' }}</dd></div>
            <div><dt class="text-slate-400">Source</dt><dd class="text-slate-800">{{ $invoice->source ?: '—' }}</dd></div>
        </dl>

        @if($invoice->review_reasons)
            <div class="mt-5 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-800">
                <span class="font-medium">Why it needs review:</span> {{ implode(' · ', $invoice->review_reasons) }}
            </div>
        @endif

        @if(!empty($invoice->line_items))
            <h3 class="text-sm font-semibold text-slate-700 mt-6 mb-2">Line items</h3>
            <table class="min-w-full text-sm">
                <thead><tr class="text-left text-slate-400 border-b border-slate-100">
                    <th class="py-1.5 pr-3">Description</th><th class="py-1.5 text-right">Amount</th></tr></thead>
                <tbody>
                @foreach($invoice->line_items as $li)
                    @php
                        $desc = is_array($li) ? ($li['description'] ?? $li['name'] ?? $li['item'] ?? '—') : (string) $li;
                        $liAmt = is_array($li) ? ($li['amount'] ?? $li['total'] ?? $li['price'] ?? null) : null;
                    @endphp
                    <tr class="border-b border-slate-50">
                        <td class="py-1.5 pr-3 text-slate-700">{{ $desc }}</td>
                        <td class="py-1.5 text-right text-slate-700">{{ $liAmt !== null ? number_format((float) $liAmt, 2) : '' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Payment --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
        <h2 class="text-sm font-semibold text-slate-700 mb-4">Payment</h2>

        @if($invoice->status === 'paid' && $payout)
            <div class="flex items-center gap-2 text-emerald-700 text-sm font-medium">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                Paid via PayPal
            </div>
            <dl class="mt-4 space-y-2 text-sm">
                @if($payout->paid_at)<div class="flex justify-between gap-3"><dt class="text-slate-400">Paid on</dt><dd class="text-slate-800">{{ $payout->paid_at->toDayDateTimeString() }}</dd></div>@endif
                <div class="flex justify-between gap-3"><dt class="text-slate-400">Reference</dt><dd class="text-slate-800 font-mono text-xs truncate max-w-[55%]" title="{{ $payout->provider_item_ref ?: $payout->provider_ref }}">{{ $payout->provider_item_ref ?: $payout->provider_ref ?: '—' }}</dd></div>
            </dl>

            @if($paymentUrl)
                <a href="{{ $paymentUrl }}" target="_blank" rel="noopener"
                   class="mt-4 inline-flex items-center gap-1.5 text-sm text-brand-600 hover:underline">
                    View payment on PayPal
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                </a>
            @else
                <p class="mt-4 text-xs text-slate-400">Paid in demo mode — no live PayPal transaction to link to.</p>
            @endif

            <form method="POST" action="{{ route('invoice.payment.remove', $invoice) }}" class="mt-5 pt-4 border-t border-slate-100"
                  onsubmit="return confirm('Remove this payment record? The invoice goes back to Needs review. This does NOT refund the money through PayPal.')">
                @csrf @method('DELETE')
                <button class="text-sm text-rose-600 hover:text-rose-700 hover:underline">Remove payment</button>
                <p class="text-[11px] text-slate-400 mt-1">Clears the record here only — no PayPal refund is issued.</p>
            </form>
        @elseif($payable)
            <p class="text-sm text-slate-500">This invoice hasn’t been paid yet.</p>
            <form method="POST" action="{{ route('invoice.pay.start', $invoice) }}" class="mt-4">@csrf
                <button class="w-full inline-flex items-center justify-center gap-2 rounded-full bg-brand-600 text-white px-4 py-2 text-sm font-medium hover:bg-brand-700">
                    Pay with PayPal
                </button>
            </form>
        @else
            <p class="text-sm text-slate-500">No payment on this invoice.</p>
        @endif
    </div>
</div>

@endsection
