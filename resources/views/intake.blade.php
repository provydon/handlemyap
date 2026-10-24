<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Send {{ $owner->name }} an invoice — HandlemyAP</title>
    @include('partials.head')
</head>
<body class="h-full text-slate-800">
@php($manual = old('vendor_name') !== null || $errors->hasAny(['vendor_name', 'vendor_email', 'amount', 'currency']))
<div class="min-h-full flex flex-col bg-gradient-to-b from-brand-50/60 to-slate-50">
    <header class="bg-white/80 backdrop-blur border-b border-slate-100">
        <div class="max-w-xl mx-auto px-5 h-14 flex items-center">
            <a href="/" class="font-semibold">Handlemy<span class="text-brand-600">AP</span></a>
        </div>
    </header>

    <main class="flex-1 max-w-xl mx-auto w-full px-5 py-10">
        @if (session('sent'))
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 text-center">
                <div class="mx-auto w-14 h-14 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600">
                    <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                </div>
                <h1 class="text-xl font-bold text-slate-900 mt-4">Invoice sent to {{ session('sent') }}</h1>
                <p class="text-slate-500 mt-2 text-sm">They’ll review it and pay you via PayPal. You can close this page.</p>
                <a href="{{ url()->current() }}" class="inline-block mt-6 text-brand-600 text-sm font-medium hover:underline">Send another</a>
            </div>
        @else
            {{-- Heading --}}
            <div class="flex items-center gap-3 mb-6">
                <div class="w-11 h-11 rounded-full bg-brand-600 text-white flex items-center justify-center font-bold text-lg shrink-0">
                    {{ strtoupper(mb_substr($owner->name, 0, 1)) }}
                </div>
                <div>
                    <h1 class="text-xl font-bold text-slate-900 leading-tight">Send {{ $owner->name }} an invoice</h1>
                    <p class="text-slate-500 text-sm">They’ll review it and pay you via PayPal.</p>
                </div>
            </div>

            {{-- UPLOAD MODE (default) --}}
            <form method="POST" action="{{ url('/u/'.$owner->handle) }}" enctype="multipart/form-data"
                  id="upload-mode" class="{{ $manual ? 'hidden' : '' }} bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                @csrf
                @if ($errors->has('file'))
                    <p class="text-sm text-rose-600 mb-3">{{ $errors->first('file') }}</p>
                @endif

                <label id="dropzone" for="file"
                       class="block cursor-pointer rounded-2xl border-2 border-dashed border-slate-300 px-6 py-12 text-center transition hover:border-brand-400 hover:bg-brand-50/50">
                    <svg class="w-12 h-12 mx-auto text-brand-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/>
                    </svg>
                    <div id="dz-text" class="mt-4">
                        <div class="font-semibold text-slate-800">Drop your invoice here</div>
                        <div class="text-sm text-slate-500 mt-1">or <span class="text-brand-600 font-medium">click to browse</span> — PDF, image or scan</div>
                    </div>
                    <div id="dz-file" class="mt-4 hidden items-center justify-center gap-2 text-sm">
                        <svg class="w-5 h-5 text-brand-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                        <span id="dz-name" class="font-medium text-slate-800 truncate max-w-[16rem]"></span>
                        <button type="button" id="dz-clear" class="text-slate-400 hover:text-rose-500 text-lg leading-none">&times;</button>
                    </div>
                    <input id="file" name="file" type="file" required class="sr-only">
                </label>

                <p class="text-xs text-slate-400 mt-3 text-center">We read the vendor, amount and due date automatically — no typing.</p>
                <button id="upload-submit" class="mt-4 w-full bg-brand-600 hover:bg-brand-700 disabled:opacity-40 disabled:cursor-not-allowed text-white rounded-full px-4 py-3 font-semibold transition" disabled>Send invoice</button>

                <p class="text-center text-sm text-slate-500 mt-4">
                    Rather type it in? <button type="button" onclick="setMode('manual')" class="text-brand-600 font-medium hover:underline">Enter details manually</button>
                </p>
            </form>

            {{-- MANUAL MODE (fallback) --}}
            <form method="POST" action="{{ url('/u/'.$owner->handle) }}"
                  id="manual-mode" class="{{ $manual ? '' : 'hidden' }} bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-4">
                @csrf
                @foreach ($errors->all() as $error)
                    <p class="text-sm text-rose-600">{{ $error }}</p>
                @endforeach

                <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2">
                        <label for="vendor_name" class="block text-sm font-medium text-slate-700 mb-1">Your business name</label>
                        <input id="vendor_name" name="vendor_name" value="{{ old('vendor_name') }}" autocomplete="organization"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-brand-500 focus:outline-none">
                    </div>
                    <div class="col-span-2">
                        <label for="vendor_email" class="block text-sm font-medium text-slate-700 mb-1">Your PayPal email (where you’ll be paid)</label>
                        <input id="vendor_email" name="vendor_email" type="email" value="{{ old('vendor_email') }}" autocomplete="email"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-brand-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="amount" class="block text-sm font-medium text-slate-700 mb-1">Amount</label>
                        <input id="amount" name="amount" type="number" step="0.01" value="{{ old('amount') }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-brand-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="currency" class="block text-sm font-medium text-slate-700 mb-1">Currency</label>
                        <input id="currency" name="currency" value="{{ old('currency', 'USD') }}" maxlength="3"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 uppercase focus:ring-2 focus:ring-brand-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="invoice_number" class="block text-sm font-medium text-slate-700 mb-1">Invoice # <span class="text-slate-400">(optional)</span></label>
                        <input id="invoice_number" name="invoice_number" value="{{ old('invoice_number') }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-brand-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="due_date" class="block text-sm font-medium text-slate-700 mb-1">Due date <span class="text-slate-400">(optional)</span></label>
                        <input id="due_date" name="due_date" type="date" value="{{ old('due_date') }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-brand-500 focus:outline-none">
                    </div>
                </div>

                <button class="w-full bg-brand-600 hover:bg-brand-700 text-white rounded-full px-4 py-3 font-semibold">Send invoice</button>

                <p class="text-center text-sm text-slate-500">
                    <button type="button" onclick="setMode('upload')" class="text-brand-600 font-medium hover:underline">← Upload a file instead</button>
                </p>
            </form>
        @endif
    </main>

    <footer class="border-t border-slate-100 bg-white">
        <div class="max-w-xl mx-auto px-5 py-4 text-xs text-slate-400 text-center">
            Powered by <a href="/" class="hover:text-slate-600">HandlemyAP</a> — AI accounts payable, paid via PayPal.
        </div>
    </footer>
</div>

<script>
    function setMode(mode) {
        document.getElementById('upload-mode').classList.toggle('hidden', mode !== 'upload');
        document.getElementById('manual-mode').classList.toggle('hidden', mode !== 'manual');
    }

    (function () {
        const input = document.getElementById('file');
        if (!input) return;
        const zone = document.getElementById('dropzone');
        const text = document.getElementById('dz-text');
        const fileBox = document.getElementById('dz-file');
        const nameEl = document.getElementById('dz-name');
        const submit = document.getElementById('upload-submit');

        function show() {
            const f = input.files && input.files[0];
            if (f) {
                nameEl.textContent = f.name;
                text.classList.add('hidden');
                fileBox.classList.remove('hidden'); fileBox.classList.add('flex');
                submit.disabled = false;
            } else {
                text.classList.remove('hidden');
                fileBox.classList.add('hidden'); fileBox.classList.remove('flex');
                submit.disabled = true;
            }
        }
        input.addEventListener('change', show);

        document.getElementById('dz-clear').addEventListener('click', function (e) {
            e.preventDefault(); e.stopPropagation();
            input.value = ''; show();
        });

        ['dragenter', 'dragover'].forEach(ev => zone.addEventListener(ev, e => {
            e.preventDefault(); zone.classList.add('border-brand-500', 'bg-brand-50');
        }));
        ['dragleave', 'drop'].forEach(ev => zone.addEventListener(ev, e => {
            e.preventDefault(); zone.classList.remove('border-brand-500', 'bg-brand-50');
        }));
        zone.addEventListener('drop', e => {
            if (e.dataTransfer.files.length) { input.files = e.dataTransfer.files; show(); }
        });
    })();
</script>
</body>
</html>
