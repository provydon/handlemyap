@extends('layout')
@section('title', 'Dashboard')
@section('content')

@php($me = auth()->user())
@php($intake = url('/u/'.$me->handle))
@php($paypalClientId = config('handlemyap.paypal.client_id'))
{{-- Smart-button popup is available when a live PayPal app is configured and we're not forced offline. --}}
@php($paypalEnabled = filled($paypalClientId) && config('handlemyap.gateway') !== 'fake')

{{-- Header: title on the left, a compact intake-link widget on the right
     (no longer a full-width banner, but still front-and-centre). --}}
<div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">Payables</h1>
        <p class="text-sm text-slate-500 max-w-xl">Vendors send invoices to your link — HandlemyAP reads and sorts each one, then you pay it with PayPal in a tap.</p>
    </div>
    <div class="shrink-0 rounded-xl border border-slate-200 bg-white px-3.5 py-3 shadow-sm">
        <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400 mb-1.5">Your invoice intake link</div>
        <div class="flex items-center gap-2">
            <input id="intake-url" readonly aria-label="Your intake link" value="{{ $intake }}"
                   class="w-48 sm:w-60 max-w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs text-slate-600">
            <button onclick="copyIntake(this)" class="rounded-full bg-brand-600 text-white px-3.5 py-1.5 text-xs font-medium hover:bg-brand-700">Copy</button>
            <a href="{{ $intake }}" target="_blank" class="rounded-full border border-slate-300 px-3.5 py-1.5 text-xs text-slate-700 hover:bg-slate-50">Open</a>
        </div>
    </div>
</div>

{{-- Metrics --}}
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
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
</div>

{{-- Add one yourself — secondary, for the odd invoice that didn't come through your link --}}
<details class="mb-6">
    <summary class="cursor-pointer select-none text-sm text-slate-500 hover:text-slate-700 py-1">
        + Add one yourself (got an invoice another way? upload or type it in)
    </summary>
    <div class="grid md:grid-cols-2 gap-4 mt-3">
        <form id="form-upload" data-source="upload" data-defer="1" method="POST" action="/api/v1/invoices" enctype="multipart/form-data"
              class="bg-white rounded-2xl border border-slate-200 p-4">
            <div class="font-medium text-slate-800 mb-2">Upload an invoice</div>
            <p class="text-xs text-slate-500 mb-3">PDF, image, or .txt/.json — any layout. The AI reads it.</p>
            <input id="upload-file" type="file" name="file" required aria-label="Invoice file"
                   class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-brand-700">
            <div class="flex items-center gap-3 mt-3">
                <button class="bg-brand-600 hover:bg-brand-700 text-white rounded-full px-4 py-2 text-sm font-medium disabled:opacity-60">Read &amp; queue</button>
                <span data-feedback class="text-xs text-slate-500"></span>
            </div>
        </form>

        <form id="form-manual" data-source="manual" method="POST" action="/api/v1/invoices"
              class="bg-white rounded-2xl border border-slate-200 p-4">
            <div class="font-medium text-slate-800 mb-2">Quick add</div>
            <div class="grid grid-cols-2 gap-2">
                <input id="qa-vendor_name" name="vendor_name" placeholder="Vendor" required aria-label="Vendor" class="col-span-2 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input id="qa-vendor_email" name="vendor_email" type="email" placeholder="Payout email" aria-label="Payout email" class="col-span-2 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input id="qa-amount" name="amount" type="number" step="0.01" placeholder="Amount" required aria-label="Amount" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input id="qa-currency" name="currency" value="{{ auth()->user()->default_currency ?? 'USD' }}" maxlength="3" aria-label="Currency" class="rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase">
                <input id="qa-due_date" name="due_date" type="date" aria-label="Due date" class="col-span-2 rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="flex items-center gap-3 mt-3">
                <button class="bg-slate-800 hover:bg-slate-900 text-white rounded-full px-4 py-2 text-sm font-medium disabled:opacity-60">Add</button>
                <span data-feedback class="text-xs text-slate-500"></span>
            </div>
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
            <select id="f-currency" aria-label="Filter by currency" class="hma-select rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none">
                <option value="">All currencies</option>
            </select>
            <select id="f-status" aria-label="Filter by status" class="hma-select rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none">
                <option value="">All statuses</option>
                <option value="needs_review">Needs review</option>
                <option value="paid">Paid</option>
                <option value="held">Held</option>
                <option value="rejected">Rejected</option>
            </select>
            <select id="f-source" aria-label="Filter by source" class="hma-select rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none">
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
    <div class="mt-3 text-center">
        <button id="load-more" onclick="fetchQueue({ reset: false })"
                class="hidden rounded-full border border-slate-300 px-5 py-2 text-sm text-slate-700 hover:bg-slate-50">Load more</button>
    </div>
</div>

<style>
    /* Custom chevron so the arrow sits inset from the right edge, not flush against it. */
    .hma-select {
        -webkit-appearance: none;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='2' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='m19.5 8.25-7.5 7.5-7.5-7.5'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0.7rem center;
        background-size: 0.9rem;
        padding-right: 2.25rem;
    }
</style>
{{-- Pay-with-PayPal popup: the PayPal window opens over this, no page leave --}}
<div id="pp-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/40" onclick="closePayModal()"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-sm p-6">
        <div class="flex items-center justify-between mb-1">
            <h3 class="font-semibold text-slate-900">Pay with PayPal</h3>
            <button onclick="closePayModal()" aria-label="Close" class="text-slate-400 hover:text-slate-600 text-xl leading-none">&times;</button>
        </div>
        <p id="pp-summary" class="text-sm text-slate-500 mb-5"></p>
        <div id="paypal-buttons"></div>
        <p id="pp-status" class="mt-3 text-sm text-center text-slate-500"></p>
        <p class="mt-3 text-[11px] text-slate-400 text-center">A PayPal window opens — log in and approve. You won't leave this page.</p>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community@32.2.0/styles/ag-grid.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community@32.2.0/styles/ag-theme-quartz.css">
<script src="https://cdn.jsdelivr.net/npm/ag-grid-community@32.2.0/dist/ag-grid-community.min.js"></script>
@if ($paypalEnabled)
<script src="https://www.paypal.com/sdk/js?client-id={{ $paypalClientId }}&currency=USD&intent=capture&components=buttons"></script>
@endif
<script>
const PAYPAL_ENABLED = @json($paypalEnabled);
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
const API_TOKEN = @json($apiToken);
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

// Actions run against the REST API and refresh the grid live — no page reload.
async function submitAction(id, verb) {
    const res = await fetch(`/api/v1/invoices/${id}/${verb}`, {
        method: 'POST',
        headers: { 'Authorization': 'Bearer ' + API_TOKEN, 'Accept': 'application/json' },
    });
    if (res.ok) await load();
    return res.ok;
}
async function onAction(id, sel) {
    const verb = sel.value;
    if (!verb) return;
    if (verb === 'pay_paypal') { sel.value = ''; startPayPal(id); return; }
    sel.disabled = true;
    await submitAction(id, verb);   // load() re-renders the grid, replacing this cell
}

// Smart-button popup when a live PayPal app is configured; otherwise the
// full-page POST (which the server simulates in demo mode).
function startPayPal(id) {
    if (PAYPAL_ENABLED && window.paypal) openPayModal(id);
    else payWithPayPal(id);
}

let ppInvoiceId = null;
let ppButtons = null;

function openPayModal(id) {
    ppInvoiceId = id;
    const inv = allInvoices.find(r => r.id === id);
    document.getElementById('pp-summary').textContent = inv
        ? `${inv.vendor || 'Vendor'} · ${inv.invoice_number || ''} · ${money(inv.amount, inv.currency || 'USD')}`
        : `Invoice #${id}`;
    ppSay('');
    document.getElementById('pp-modal').classList.remove('hidden');
    renderPayPalButtons();
}

function closePayModal() {
    document.getElementById('pp-modal').classList.add('hidden');
    ppInvoiceId = null;
}

function ppSay(msg, err) {
    const el = document.getElementById('pp-status');
    if (el) { el.textContent = msg; el.className = 'mt-3 text-sm text-center ' + (err ? 'text-rose-600' : 'text-slate-500'); }
}

function renderPayPalButtons() {
    const container = document.getElementById('paypal-buttons');
    container.innerHTML = '';
    ppButtons = paypal.Buttons({
        style: { layout: 'vertical', color: 'blue', shape: 'pill', label: 'paypal', height: 44 },
        createOrder: async () => {
            const res = await fetch(`/app/invoices/${ppInvoiceId}/pay/paypal/order`, {
                method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
            });
            const d = await res.json();
            if (!res.ok) { ppSay(d.message || 'Could not start payment.', true); throw new Error(d.message || 'order failed'); }
            return d.id;
        },
        onApprove: async (data) => {
            ppSay('Finishing payment…');
            const res = await fetch(`/app/invoices/${ppInvoiceId}/pay/paypal/capture`, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({ order_id: data.orderID }),
            });
            const d = await res.json();
            if (res.ok && d.ok) {
                ppSay('Paid ✓');
                setTimeout(async () => { closePayModal(); await load(); }, 800);
            } else {
                ppSay(d.message || 'Payment could not be completed.', true);
            }
        },
        onCancel: () => ppSay('Payment canceled.'),
        onError: () => ppSay('PayPal error — please retry.', true),
    });
    ppButtons.render('#paypal-buttons');
}

// Fallback: a full-page POST so the server can send the browser on to PayPal's
// approve page (or, in demo mode, straight back with a "paid" flash).
function payWithPayPal(id) {
    const f = document.createElement('form');
    f.method = 'POST';
    f.action = `/app/invoices/${id}/pay/paypal`;
    const t = document.createElement('input');
    t.type = 'hidden'; t.name = '_token'; t.value = CSRF;
    f.appendChild(t);
    document.body.appendChild(f);
    f.submit();
}

function actionMenu(id, status) {
    if (!ACTIONABLE.includes(status)) return '<span class="text-slate-300 text-xs">—</span>';
    return `<select onchange="onAction(${id}, this)" aria-label="Invoice actions"
            class="hma-select rounded-full border border-slate-300 bg-white pl-3 py-1 text-xs text-slate-700 hover:bg-slate-50 focus:outline-none">
        <option value="">Actions</option>
        <option value="pay_paypal">Pay with PayPal</option>
        <option value="hold">Hold</option>
        <option value="reject">Reject</option>
    </select>`;
}

let gridApi = null;
let allInvoices = [];

const setStat = (k, v) => { const el = document.querySelector(`[data-stat="${k}"]`); if (el) el.textContent = v; };
const subStat = (k, v) => { const el = document.querySelector(`[data-substat="${k}"]`); if (el) el.textContent = v; };

function renderStats(stats) {
    setStat('needs_review', stats.needs_review); subStat('needs_review', money(stats.needs_review_amount, 'USD') + ' to pay');
    setStat('paid', stats.paid);                 subStat('paid', money(stats.paid_amount, 'USD') + ' paid');
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
    { headerName: 'Payment', maxWidth: 110, cellRenderer: p => paymentCell(p.data) },
    { headerName: 'Action', width: 160, flex: 0, pinned: 'right', resizable: false, sortable: false, cellRenderer: p => actionMenu(p.data.id, p.data.status) },
];

// Paid invoices link out to their PayPal transaction; demo-mode payments have
// no real transaction, so they show a muted "demo" marker instead of a link.
function paymentCell(d) {
    if (d.payment_url) return `<a href="${d.payment_url}" target="_blank" rel="noopener" class="text-brand-600 hover:underline">PayPal&nbsp;↗</a>`;
    if (d.status === 'paid') return `<span class="text-slate-400" title="Paid in demo mode — no live PayPal transaction">demo</span>`;
    return '';
}

function filterParams() {
    const p = new URLSearchParams();
    const q = (document.getElementById('f-search').value || '').trim();
    const st = document.getElementById('f-status').value;
    const src = document.getElementById('f-source').value;
    const ccy = document.getElementById('f-currency').value;
    if (q) p.set('search', q);
    if (st) p.set('status', st);
    if (src) p.set('source', src);
    if (ccy) p.set('currency', ccy);
    return p;
}

function filterStatus(status) {
    document.getElementById('f-status').value = status;
    fetchQueue({ reset: true });
    document.getElementById('grid').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

let nextPage = 1;
let hasMore = false;
let loadingQueue = false;

// The queue is filtered + paginated server-side; the client holds only the rows
// it's shown. reset:true fetches a fresh page 1; reset:false appends the next.
async function fetchQueue({ reset = true } = {}) {
    if (loadingQueue) return;
    loadingQueue = true;
    try {
        const p = filterParams();
        p.set('page', reset ? 1 : nextPage);
        const res = await fetch('/app/queue?' + p.toString());
        const data = await res.json();
        allInvoices = reset ? (data.invoices || []) : allInvoices.concat(data.invoices || []);
        hasMore = !!data.has_more;
        nextPage = data.next_page;
        renderQueue();
    } finally {
        loadingQueue = false;
    }
}

function renderQueue() {
    const gridEl = document.getElementById('grid');
    const fb = document.getElementById('fallback');
    const emptyEl = document.getElementById('empty');
    document.getElementById('load-more').classList.toggle('hidden', !hasMore);

    if (!allInvoices.length) {
        gridEl.classList.add('hidden'); fb.classList.add('hidden');
        emptyEl.classList.remove('hidden');
        emptyEl.textContent = [...filterParams().keys()].length
            ? 'No invoices match your filters.'
            : 'No invoices yet — share your intake link above and they’ll show up here.';
        return;
    }

    emptyEl.classList.add('hidden');
    if (gridApi) { gridEl.classList.remove('hidden'); fb.classList.add('hidden'); gridApi.setGridOption('rowData', allInvoices); }
    else { renderFallback(allInvoices); }
}

function populateCurrencies(list) {
    const sel = document.getElementById('f-currency');
    const current = sel.value;
    sel.innerHTML = '<option value="">All currencies</option>' +
        (list || []).map(c => `<option value="${c}">${c}</option>`).join('');
    sel.value = (list || []).includes(current) ? current : '';  // keep selection if still present
}

function initGrid() {
    if (window.agGrid && agGrid.createGrid && !gridApi) {
        gridApi = agGrid.createGrid(document.getElementById('grid'), {
            rowData: [],
            defaultColDef: { resizable: true, sortable: true, flex: 1 },
            columnDefs: COLUMNS,
            rowClass: 'cursor-pointer',
            onRowClicked: onRowClicked,
        });
    }
}

// Clicking a row opens the invoice — but not when the click lands on an
// interactive control in the row (the Actions menu, the Doc/Payment links).
function onRowClicked(e) {
    const t = e.event && e.event.target;
    if (t && t.closest('a, button, select, input, label')) return;
    if (e.data && e.data.id) window.location = `/app/invoices/${e.data.id}`;
}
function rowNav(ev, id) {
    if (ev.target.closest('a, button, select, input, label')) return;
    window.location = `/app/invoices/${id}`;
}

// Summary metrics — one grouped DB aggregate, independent of the queue page.
async function loadSummary() {
    const res = await fetch('/app/summary');
    const data = await res.json();
    renderStats(data.stats);
    populateCurrencies(data.currencies);
}

async function load() {
    initGrid();
    await Promise.all([loadSummary(), fetchQueue({ reset: true })]); // cheap DB calls, in parallel
}

function renderFallback(rows) {
    document.getElementById('grid').classList.add('hidden');
    const el = document.getElementById('fallback');
    el.classList.remove('hidden');
    el.innerHTML = `<table class="min-w-full text-sm">
        <thead><tr class="text-left text-slate-400 border-b">
            <th class="py-2 pr-3">Vendor</th><th class="pr-3">Amount</th><th class="pr-3">Due</th>
            <th class="pr-3">Status</th><th class="pr-3">Source</th><th class="pr-3">Why / note</th><th class="pr-3">Doc</th><th class="pr-3">Payment</th><th>Action</th></tr></thead>
        <tbody>${rows.map(r => `<tr class="border-b border-slate-100 cursor-pointer hover:bg-slate-50" onclick="rowNav(event, ${r.id})">
            <td class="py-2 pr-3">${r.vendor ?? ''}</td>
            <td class="pr-3">${money(r.amount, r.currency)}</td>
            <td class="pr-3">${r.due_date ?? ''}</td>
            <td class="pr-3">${badge(r.status)}</td>
            <td class="pr-3 text-slate-500">${r.source ?? ''}</td>
            <td class="pr-3 text-slate-500">${r.reasons ?? ''}</td>
            <td class="pr-3">${r.file_url ? `<a href="${r.file_url}" target="_blank" class="text-brand-600 hover:underline">view</a>` : ''}</td>
            <td class="pr-3">${paymentCell(r)}</td>
            <td>${actionMenu(r.id, r.status)}</td></tr>`).join('')}</tbody></table>`;
}

// Filter changes refetch from the server (page 1). Debounce the search box so
// we don't fire a request per keystroke.
let searchTimer;
document.getElementById('f-search').addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => fetchQueue({ reset: true }), 300);
});
['f-status','f-source','f-currency'].forEach(id => {
    document.getElementById(id).addEventListener('change', () => fetchQueue({ reset: true }));
});

// ---- Add an invoice via the public REST API (the dashboard dogfoods /api/v1) ----
// On success we re-pull the feed so the metrics + queue update in real time,
// no page reload.
function wireInvoiceForm(id) {
    const form = document.getElementById(id);
    if (!form) return;
    const btn = form.querySelector('button');
    const feedback = form.querySelector('[data-feedback]');
    const say = (msg, cls) => { feedback.textContent = msg; feedback.className = 'text-xs ' + (cls || 'text-slate-500'); };

    const defer = form.dataset.defer === '1';

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const body = new FormData(form);
        body.append('source', form.dataset.source);
        if (defer) body.append('defer', '1');

        btn.disabled = true;
        say('Sending…');
        try {
            const res = await fetch('/api/v1/invoices', {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + API_TOKEN, 'Accept': 'application/json' },
                body,
            });
            if (res.status !== 201) {
                if (res.status === 422) {
                    const { message, errors } = await res.json();
                    say(errors ? Object.values(errors)[0][0] : (message || 'Check the fields and try again.'), 'text-rose-600');
                } else {
                    say('Something went wrong (' + res.status + ').', 'text-rose-600');
                }
                return;
            }

            const inv = await res.json();
            form.reset();

            if (defer) {
                // Phase 1 done: the file is on S3 and the AI read is queued as a
                // background job. Confirm instantly, show the "reading…" row, then
                // poll until the worker finishes and fill the row live.
                say('Uploaded ✓ — AI is reading it in the background…', 'text-emerald-600 font-medium');
                await load();
                document.getElementById('grid').scrollIntoView({ behavior: 'smooth', block: 'nearest' });

                const done = await waitForRead(inv.id);
                if (done) say(decisionLine(done), 'text-emerald-600 font-medium');
            } else {
                say(decisionLine(inv), 'text-emerald-600 font-medium');
                await load();
                document.getElementById('grid').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        } catch (err) {
            say('Network error — please retry.', 'text-rose-600');
        } finally {
            btn.disabled = false;
        }
    });
}
const sleep = ms => new Promise(r => setTimeout(r, ms));

// Poll the feed while the background job reads the invoice. If no worker picks
// it up in time (e.g. the queue worker isn't running), fall back to reading it
// synchronously so the UI never hangs on "reading…".
async function waitForRead(id) {
    for (let i = 0; i < 6; i++) {
        await sleep(2500);
        await fetchQueue({ reset: true });   // queue feed only — don't re-poll the rail each tick
        const inv = allInvoices.find(r => r.id === id);
        if (inv && inv.status !== 'extracting') { loadSummary(); return inv; }
    }
    const res = await fetch(`/api/v1/invoices/${id}/extract`, {
        method: 'POST',
        headers: { 'Authorization': 'Bearer ' + API_TOKEN, 'Accept': 'application/json' },
    });
    await Promise.all([fetchQueue({ reset: true }), loadSummary()]);
    return res.ok ? await res.json() : allInvoices.find(r => r.id === id);
}

function decisionLine(inv) {
    const v = inv.vendor?.name || inv.vendor_name || 'Invoice';
    const map = {
        paid:         `✓ ${v} paid`,
        needs_review: `Added — ${v} needs your review`,
    };
    return map[inv.status] || `Added — ${v} (${(inv.status || '').replace('_',' ')})`;
}
wireInvoiceForm('form-upload');
wireInvoiceForm('form-manual');

load();
</script>
@endsection
