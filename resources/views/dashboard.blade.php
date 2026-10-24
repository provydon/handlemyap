@extends('layout')
@section('title', 'Dashboard')
@section('content')

<div class="mb-5">
    <h1 class="text-xl font-semibold text-slate-900">Payables</h1>
    <p class="text-sm text-slate-500">Vendors send invoices to your link — HandlemyAP reads each one, pays via PayPal under your rules, and flags anything off for you to review.</p>
</div>

{{-- Intake link — the main way invoices arrive --}}
@php($intake = url('/u/'.auth()->user()->handle))
<div class="bg-brand-600 rounded-2xl p-5 mb-6 text-white">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <div class="font-medium">Your invoice intake link</div>
            <p class="text-sm text-brand-100 mt-0.5">Share it with your vendors — their invoices land straight in the queue below. No more digging through email or WhatsApp.</p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <input id="intake-url" readonly aria-label="Your intake link" value="{{ $intake }}"
                   class="rounded-lg bg-white/10 border border-white/20 px-3 py-2 text-sm text-white w-56 max-w-full">
            <button onclick="copyIntake(this)" class="rounded-full bg-white text-brand-700 px-4 py-2 text-sm font-medium hover:bg-brand-50">Copy</button>
            <a href="{{ $intake }}" target="_blank" class="rounded-full border border-white/30 px-4 py-2 text-sm hover:bg-white/10">Open</a>
        </div>
    </div>
</div>

{{-- Metrics --}}
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    {{-- Needs review --}}
    <div onclick="filterStatus('needs_review')" class="bg-white rounded-2xl border border-slate-200 shadow-sm px-5 py-4 cursor-pointer transition hover:-translate-y-0.5 hover:shadow-md">
        <div class="text-sm font-bold text-slate-700">Needs review</div>
        <div class="flex items-center mt-3">
            <div class="h-14 w-14 rounded-xl text-white flex items-center justify-center bg-amber-500 shrink-0">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
            </div>
            <div class="ml-3 min-w-0">
                <div class="text-4xl font-semibold leading-none text-slate-900" data-stat="needs_review">—</div>
                <div class="text-xs text-slate-400 mt-1 truncate" data-substat="needs_review"></div>
            </div>
        </div>
    </div>
    {{-- Scheduled --}}
    <div onclick="filterStatus('scheduled')" class="bg-white rounded-2xl border border-slate-200 shadow-sm px-5 py-4 cursor-pointer transition hover:-translate-y-0.5 hover:shadow-md">
        <div class="text-sm font-bold text-slate-700">Scheduled</div>
        <div class="flex items-center mt-3">
            <div class="h-14 w-14 rounded-xl text-white flex items-center justify-center bg-sky-500 shrink-0">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
            </div>
            <div class="ml-3 min-w-0">
                <div class="text-4xl font-semibold leading-none text-slate-900" data-stat="scheduled">—</div>
                <div class="text-xs text-slate-400 mt-1 truncate" data-substat="scheduled"></div>
            </div>
        </div>
    </div>
    {{-- Paid --}}
    <div onclick="filterStatus('paid')" class="bg-white rounded-2xl border border-slate-200 shadow-sm px-5 py-4 cursor-pointer transition hover:-translate-y-0.5 hover:shadow-md">
        <div class="text-sm font-bold text-slate-700">Paid</div>
        <div class="flex items-center mt-3">
            <div class="h-14 w-14 rounded-xl text-white flex items-center justify-center bg-emerald-500 shrink-0">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
            </div>
            <div class="ml-3 min-w-0">
                <div class="text-4xl font-semibold leading-none text-slate-900" data-stat="paid">—</div>
                <div class="text-xs text-slate-400 mt-1 truncate" data-substat="paid"></div>
            </div>
        </div>
    </div>
    {{-- PayPal balance --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm px-5 py-4 transition hover:shadow-md">
        <div class="flex items-center justify-between">
            <div class="text-sm font-bold text-slate-700">PayPal balance</div>
            <span class="text-[10px] font-semibold uppercase tracking-wide text-brand-600 bg-brand-50 rounded px-1.5 py-0.5">PayPal</span>
        </div>
        <div class="flex items-center mt-3">
            <div class="h-14 w-14 rounded-xl text-white flex items-center justify-center bg-brand-600 shrink-0">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 0 0-2.25-2.25H15a3 3 0 1 1-6 0H5.25A2.25 2.25 0 0 0 3 12m18 0v6a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18v-6m18 0V9a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 9v3"/></svg>
            </div>
            <div class="ml-3 min-w-0">
                <div class="text-4xl font-semibold leading-none text-slate-900 truncate" data-stat="paypal_balance">—</div>
                <div class="text-xs text-slate-400 mt-1 truncate" data-substat="paypal_balance"></div>
            </div>
        </div>
    </div>
</div>

{{-- Add one yourself — secondary, for the odd invoice that didn't come through your link --}}
<details class="mb-6">
    <summary class="cursor-pointer select-none text-sm text-slate-500 hover:text-slate-700 py-1">
        + Add one yourself (got an invoice another way? upload or type it in)
    </summary>
    <div class="grid md:grid-cols-2 gap-4 mt-3">
        <form method="POST" action="/app/invoices" enctype="multipart/form-data"
              class="bg-white rounded-2xl border border-slate-200 p-4">
            @csrf
            <div class="font-medium text-slate-800 mb-2">Upload an invoice</div>
            <p class="text-xs text-slate-500 mb-3">PDF, image, or .txt/.json — any layout. The AI reads it.</p>
            <input id="upload-file" type="file" name="file" required aria-label="Invoice file"
                   class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-brand-700">
            <button class="mt-3 bg-brand-600 hover:bg-brand-700 text-white rounded-full px-4 py-2 text-sm font-medium">Read & queue</button>
        </form>

        <form method="POST" action="/app/invoices" class="bg-white rounded-2xl border border-slate-200 p-4">
            @csrf
            <div class="font-medium text-slate-800 mb-2">Quick add</div>
            <div class="grid grid-cols-2 gap-2">
                <input id="qa-vendor_name" name="vendor_name" placeholder="Vendor" required aria-label="Vendor" class="col-span-2 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input id="qa-vendor_email" name="vendor_email" type="email" placeholder="Payout email" aria-label="Payout email" class="col-span-2 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input id="qa-amount" name="amount" type="number" step="0.01" placeholder="Amount" required aria-label="Amount" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input id="qa-currency" name="currency" value="USD" maxlength="3" aria-label="Currency" class="rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase">
                <input id="qa-due_date" name="due_date" type="date" aria-label="Due date" class="col-span-2 rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <button class="mt-3 bg-slate-800 hover:bg-slate-900 text-white rounded-full px-4 py-2 text-sm font-medium">Add</button>
        </form>
    </div>
</details>

{{-- Queue --}}
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
        <div class="font-medium text-slate-800">Invoice queue</div>
        <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
            <div class="relative">
                <svg class="h-4 w-4 absolute left-2 top-1/2 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                <input id="f-search" type="search" placeholder="Search vendor or invoice #" aria-label="Search invoices"
                       class="w-full sm:w-64 bg-transparent border-0 border-b border-slate-200 pl-8 py-2 text-sm focus:outline-none focus:border-brand-500">
            </div>
            <select id="f-status" aria-label="Filter by status" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none">
                <option value="">All statuses</option>
                <option value="needs_review">Needs review</option>
                <option value="scheduled">Scheduled</option>
                <option value="paying">Paying</option>
                <option value="paid">Paid</option>
                <option value="held">Held</option>
                <option value="rejected">Rejected</option>
                <option value="failed">Failed</option>
            </select>
            <select id="f-source" aria-label="Filter by source" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none">
                <option value="">All sources</option>
                <option value="intake">Intake link</option>
                <option value="upload">Upload</option>
                <option value="api">API</option>
                <option value="mcp">MCP</option>
                <option value="manual">Manual</option>
            </select>
        </div>
    </div>
    <div id="grid" class="ag-theme-quartz" style="height: 460px; width: 100%;"></div>
    <div id="fallback" class="hidden overflow-x-auto"></div>
    <div id="empty" class="hidden text-center text-slate-400 py-10 text-sm">No invoices yet — share your intake link above and they’ll show up here.</div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community@32.2.0/styles/ag-grid.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community@32.2.0/styles/ag-theme-quartz.css">
<script src="https://cdn.jsdelivr.net/npm/ag-grid-community@32.2.0/dist/ag-grid-community.min.js"></script>
<script>
function copyIntake(btn) {
    const input = document.getElementById('intake-url');
    const prev = btn.textContent;
    const ok = () => { btn.textContent = 'Copied ✓'; setTimeout(() => { btn.textContent = prev; }, 1500); };
    const fallback = () => {
        // Works over plain http (where navigator.clipboard is unavailable).
        input.focus(); input.select(); input.setSelectionRange(0, 99999);
        try { document.execCommand('copy') ? ok() : (btn.textContent = 'Press ⌘C'); }
        catch (e) { btn.textContent = 'Press ⌘C'; }
    };
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(input.value).then(ok).catch(fallback);
    } else {
        fallback();
    }
}

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
    const btn = (verb, label, cls) => `<button onclick="submitAction(${id},'${verb}')" class="px-3 py-1 rounded-full text-xs font-medium ${cls}">${label}</button>`;
    return `<div class="flex gap-1">
        ${btn('approve','Approve & pay','bg-brand-600 text-white hover:bg-brand-700')}
        ${btn('hold','Hold','bg-slate-100 text-slate-700 hover:bg-slate-200')}
        ${btn('reject','Reject','bg-rose-50 text-rose-600 hover:bg-rose-100')}
    </div>`;
}

let gridApi = null;
let allInvoices = [];

function renderStats(stats, balance) {
    const set = (k, v) => { const el = document.querySelector(`[data-stat="${k}"]`); if (el) el.textContent = v; };
    const sub = (k, v) => { const el = document.querySelector(`[data-substat="${k}"]`); if (el) el.textContent = v; };
    set('needs_review', stats.needs_review); sub('needs_review', money(stats.needs_review_amount, 'USD') + ' pending');
    set('scheduled', stats.scheduled);       sub('scheduled', money(stats.scheduled_amount, 'USD') + ' scheduled');
    set('paid', stats.paid);                 sub('paid', money(stats.paid_amount, 'USD') + ' paid');
    if (balance) { set('paypal_balance', money(balance.available, balance.currency)); sub('paypal_balance', balance.demo ? 'Sandbox (demo)' : 'Available'); }
    else { set('paypal_balance', '—'); sub('paypal_balance', 'Not connected'); }
}

const COLUMNS = [
    { headerName: 'Vendor', field: 'vendor', minWidth: 140 },
    { headerName: 'Invoice #', field: 'invoice_number', maxWidth: 120 },
    { headerName: 'Amount', valueGetter: p => money(p.data.amount, p.data.currency), maxWidth: 130 },
    { headerName: 'Due', field: 'due_date', maxWidth: 120 },
    { headerName: 'Status', field: 'status', maxWidth: 140, cellRenderer: p => badge(p.value) },
    { headerName: 'Source', field: 'source', maxWidth: 110 },
    { headerName: 'Conf.', field: 'confidence', maxWidth: 80, valueFormatter: p => p.value == null ? '' : p.value + '%' },
    { headerName: 'Why / note', field: 'reasons', minWidth: 160, tooltipField: 'reasons' },
    { headerName: 'Doc', maxWidth: 70, cellRenderer: p => p.data.file_url ? `<a href="${p.data.file_url}" target="_blank" class="text-brand-600 hover:underline">view</a>` : '' },
    { headerName: 'Action', minWidth: 220, cellRenderer: p => actionButtons(p.data.id, p.data.status) },
];

function filterStatus(status) {
    document.getElementById('f-status').value = status;
    applyFilters();
    document.getElementById('grid').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function applyFilters() {
    const q = (document.getElementById('f-search').value || '').toLowerCase().trim();
    const st = document.getElementById('f-status').value;
    const src = document.getElementById('f-source').value;

    const filtered = allInvoices.filter(r => {
        if (st && r.status !== st) return false;
        if (src && r.source !== src) return false;
        if (q && !`${r.vendor||''} ${r.invoice_number||''} ${r.email||''}`.toLowerCase().includes(q)) return false;
        return true;
    });

    const gridEl = document.getElementById('grid');
    const fb = document.getElementById('fallback');
    const emptyEl = document.getElementById('empty');

    if (!filtered.length) {
        gridEl.classList.add('hidden'); fb.classList.add('hidden');
        emptyEl.classList.remove('hidden');
        emptyEl.textContent = allInvoices.length
            ? 'No invoices match your filters.'
            : 'No invoices yet — share your intake link above and they’ll show up here.';
        return;
    }

    emptyEl.classList.add('hidden');
    if (gridApi) { gridEl.classList.remove('hidden'); fb.classList.add('hidden'); gridApi.setGridOption('rowData', filtered); }
    else { renderFallback(filtered); }
}

async function load() {
    const res = await fetch('/app/data');
    const data = await res.json();
    allInvoices = data.invoices || [];
    renderStats(data.stats, data.paypal_balance);

    if (window.agGrid && agGrid.createGrid && !gridApi) {
        gridApi = agGrid.createGrid(document.getElementById('grid'), {
            rowData: [],
            defaultColDef: { resizable: true, sortable: true, flex: 1 },
            columnDefs: COLUMNS,
        });
    }
    applyFilters();
}

function renderFallback(rows) {
    document.getElementById('grid').classList.add('hidden');
    const el = document.getElementById('fallback');
    el.classList.remove('hidden');
    el.innerHTML = `<table class="min-w-full text-sm">
        <thead><tr class="text-left text-slate-400 border-b">
            <th class="py-2 pr-3">Vendor</th><th class="pr-3">Amount</th><th class="pr-3">Due</th>
            <th class="pr-3">Status</th><th class="pr-3">Source</th><th class="pr-3">Why / note</th><th class="pr-3">Doc</th><th>Action</th></tr></thead>
        <tbody>${rows.map(r => `<tr class="border-b border-slate-100">
            <td class="py-2 pr-3">${r.vendor ?? ''}</td>
            <td class="pr-3">${money(r.amount, r.currency)}</td>
            <td class="pr-3">${r.due_date ?? ''}</td>
            <td class="pr-3">${badge(r.status)}</td>
            <td class="pr-3 text-slate-500">${r.source ?? ''}</td>
            <td class="pr-3 text-slate-500">${r.reasons ?? ''}</td>
            <td class="pr-3">${r.file_url ? `<a href="${r.file_url}" target="_blank" class="text-brand-600 hover:underline">view</a>` : ''}</td>
            <td>${actionButtons(r.id, r.status)}</td></tr>`).join('')}</tbody></table>`;
}

['f-search','f-status','f-source'].forEach(id => {
    const el = document.getElementById(id);
    el.addEventListener('input', applyFilters);
    el.addEventListener('change', applyFilters);
});

load();
</script>
@endsection
