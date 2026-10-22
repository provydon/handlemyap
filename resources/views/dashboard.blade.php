@extends('layout')
@section('title', 'Dashboard')
@section('content')

<div class="flex items-center justify-between mb-5">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Payables</h1>
        <p class="text-sm text-slate-500">Drop an invoice in — handlemyap reads it, applies your rules, and pays via PayPal.</p>
    </div>
</div>

{{-- Stats --}}
<div id="stats" class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
    @foreach (['needs_review' => ['Needs review','amber'], 'scheduled' => ['Scheduled','sky'], 'paid' => ['Paid','emerald'], 'paid_amount' => ['Paid total','indigo']] as $key => [$label, $c])
    <div class="bg-white rounded-xl border border-slate-200 p-4">
        <div class="text-xs uppercase tracking-wide text-slate-400">{{ $label }}</div>
        <div class="text-2xl font-semibold text-{{ $c }}-600 mt-1" data-stat="{{ $key }}">—</div>
    </div>
    @endforeach
</div>

{{-- Add invoice --}}
<div class="grid md:grid-cols-2 gap-4 mb-6">
    <form method="POST" action="/app/invoices" enctype="multipart/form-data"
          class="bg-white rounded-xl border border-slate-200 p-4">
        @csrf
        <div class="font-medium text-slate-800 mb-2">Upload an invoice</div>
        <p class="text-xs text-slate-500 mb-3">PDF, image, or .txt/.json — any layout. The AI reads it.</p>
        <input type="file" name="file" required
               class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-indigo-700">
        <button class="mt-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-4 py-2 text-sm font-medium">Read & queue</button>
    </form>

    <form method="POST" action="/app/invoices" class="bg-white rounded-xl border border-slate-200 p-4">
        @csrf
        <div class="font-medium text-slate-800 mb-2">Quick add</div>
        <div class="grid grid-cols-2 gap-2">
            <input name="vendor_name" placeholder="Vendor" required class="col-span-2 rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <input name="vendor_email" type="email" placeholder="Payout email" class="col-span-2 rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <input name="amount" type="number" step="0.01" placeholder="Amount" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <input name="currency" value="USD" maxlength="3" class="rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase">
            <input name="due_date" type="date" class="col-span-2 rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <button class="mt-3 bg-slate-800 hover:bg-slate-900 text-white rounded-lg px-4 py-2 text-sm font-medium">Add</button>
    </form>
</div>

{{-- Queue --}}
<div class="bg-white rounded-xl border border-slate-200 p-4">
    <div class="font-medium text-slate-800 mb-3">Invoice queue</div>
    <div id="grid" class="ag-theme-quartz" style="height: 460px; width: 100%;"></div>
    <div id="fallback" class="hidden overflow-x-auto"></div>
    <div id="empty" class="hidden text-center text-slate-400 py-10 text-sm">No invoices yet — add one above.</div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community@32.2.0/styles/ag-grid.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community@32.2.0/styles/ag-theme-quartz.css">
<script src="https://cdn.jsdelivr.net/npm/ag-grid-community@32.2.0/dist/ag-grid-community.min.js"></script>
<script>
const CSRF = document.querySelector('meta[name=csrf-token]').content;
const money = (a, c) => a == null ? '' : new Intl.NumberFormat('en-US', {style:'currency', currency: c || 'USD'}).format(a);

const BADGE = {
    needs_review: ['Needs review','bg-amber-100 text-amber-800'],
    scheduled:    ['Scheduled','bg-sky-100 text-sky-800'],
    paying:       ['Paying…','bg-sky-100 text-sky-800'],
    paid:         ['Paid','bg-emerald-100 text-emerald-800'],
    held:         ['Held','bg-slate-200 text-slate-700'],
    rejected:     ['Rejected','bg-slate-200 text-slate-500'],
    failed:       ['Failed','bg-rose-100 text-rose-700'],
    approved:     ['Approved','bg-emerald-100 text-emerald-800'],
    received:     ['Received','bg-slate-100 text-slate-600'],
    extracting:   ['Reading…','bg-slate-100 text-slate-600'],
};
const badge = s => { const [t,c] = BADGE[s] || [s,'bg-slate-100 text-slate-600']; return `<span class="px-2 py-0.5 rounded-full text-xs font-medium ${c}">${t}</span>`; };
const ACTIONABLE = ['needs_review','scheduled','held'];

function submitAction(id, verb) {
    const f = document.createElement('form');
    f.method = 'POST'; f.action = `/app/invoices/${id}/${verb}`;
    f.innerHTML = `<input type="hidden" name="_token" value="${CSRF}">`;
    document.body.appendChild(f); f.submit();
}
function actionButtons(id, status) {
    if (!ACTIONABLE.includes(status)) return '';
    const btn = (verb, label, cls) => `<button onclick="submitAction(${id},'${verb}')" class="px-2 py-1 rounded text-xs font-medium ${cls}">${label}</button>`;
    return `<div class="flex gap-1">
        ${btn('approve','Approve & pay','bg-indigo-600 text-white hover:bg-indigo-700')}
        ${btn('hold','Hold','bg-slate-100 text-slate-700 hover:bg-slate-200')}
        ${btn('reject','Reject','bg-rose-50 text-rose-600 hover:bg-rose-100')}
    </div>`;
}

async function load() {
    const res = await fetch('/app/data');
    const { invoices, stats } = await res.json();

    document.querySelectorAll('[data-stat]').forEach(el => {
        const k = el.dataset.stat;
        el.textContent = k === 'paid_amount' ? money(stats.paid_amount, 'USD') : stats[k];
    });

    const gridEl = document.getElementById('grid');
    const emptyEl = document.getElementById('empty');
    if (!invoices.length) { gridEl.classList.add('hidden'); emptyEl.classList.remove('hidden'); return; }

    if (window.agGrid && agGrid.createGrid) {
        agGrid.createGrid(gridEl, {
            rowData: invoices,
            defaultColDef: { resizable: true, sortable: true, flex: 1 },
            columnDefs: [
                { headerName: 'Vendor', field: 'vendor', minWidth: 140 },
                { headerName: 'Invoice #', field: 'invoice_number', maxWidth: 120 },
                { headerName: 'Amount', valueGetter: p => money(p.data.amount, p.data.currency), maxWidth: 130 },
                { headerName: 'Due', field: 'due_date', maxWidth: 120 },
                { headerName: 'Status', field: 'status', maxWidth: 140, cellRenderer: p => badge(p.value) },
                { headerName: 'Conf.', field: 'confidence', maxWidth: 80, valueFormatter: p => p.value == null ? '' : p.value + '%' },
                { headerName: 'Why / note', field: 'reasons', minWidth: 160, tooltipField: 'reasons' },
                { headerName: 'Action', minWidth: 220, cellRenderer: p => actionButtons(p.data.id, p.data.status) },
            ],
        });
    } else {
        renderFallback(invoices); // CDN failed — still usable
    }
}

function renderFallback(rows) {
    document.getElementById('grid').classList.add('hidden');
    const el = document.getElementById('fallback');
    el.classList.remove('hidden');
    el.innerHTML = `<table class="min-w-full text-sm">
        <thead><tr class="text-left text-slate-400 border-b">
            <th class="py-2 pr-3">Vendor</th><th class="pr-3">Amount</th><th class="pr-3">Due</th>
            <th class="pr-3">Status</th><th class="pr-3">Why / note</th><th>Action</th></tr></thead>
        <tbody>${rows.map(r => `<tr class="border-b border-slate-100">
            <td class="py-2 pr-3">${r.vendor ?? ''}</td>
            <td class="pr-3">${money(r.amount, r.currency)}</td>
            <td class="pr-3">${r.due_date ?? ''}</td>
            <td class="pr-3">${badge(r.status)}</td>
            <td class="pr-3 text-slate-500">${r.reasons ?? ''}</td>
            <td>${actionButtons(r.id, r.status)}</td></tr>`).join('')}</tbody></table>`;
}

load();
</script>
@endsection
