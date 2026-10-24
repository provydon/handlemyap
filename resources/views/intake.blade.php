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
<div class="min-h-full flex flex-col">
    <header class="border-b border-slate-100 bg-white">
        <div class="max-w-xl mx-auto px-5 h-14 flex items-center">
            <a href="/" class="font-semibold">Handlemy<span class="text-brand-600">AP</span></a>
        </div>
    </header>

    <main class="flex-1 max-w-xl mx-auto w-full px-5 py-10">
        @if (session('sent'))
            <div class="bg-white rounded-2xl border border-slate-200 p-8 text-center">
                <div class="mx-auto w-12 h-12 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600 text-2xl">✓</div>
                <h1 class="text-xl font-semibold text-slate-900 mt-4">Invoice sent to {{ session('sent') }}</h1>
                <p class="text-slate-500 mt-2 text-sm">They’ll review it and pay you via PayPal. You can close this page.</p>
                <a href="{{ url()->current() }}" class="inline-block mt-6 text-brand-600 text-sm hover:underline">Send another</a>
            </div>
        @else
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-slate-900">Send {{ $owner->name }} an invoice</h1>
                <p class="text-slate-500 mt-1 text-sm">Fill this in and {{ $owner->name }} gets it straight in their payables — they’ll pay you via PayPal.</p>
            </div>

            <form method="POST" action="{{ url('/u/'.$owner->handle) }}" enctype="multipart/form-data"
                  class="bg-white rounded-2xl border border-slate-200 p-6 space-y-4">
                @csrf
                @foreach ($errors->all() as $error)
                    <p class="text-sm text-rose-600">{{ $error }}</p>
                @endforeach

                <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2">
                        <label for="vendor_name" class="block text-sm font-medium text-slate-700 mb-1">Your business name</label>
                        <input id="vendor_name" name="vendor_name" value="{{ old('vendor_name') }}" required autocomplete="organization"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-brand-500 focus:outline-none">
                    </div>
                    <div class="col-span-2">
                        <label for="vendor_email" class="block text-sm font-medium text-slate-700 mb-1">Your PayPal email (where you’ll be paid)</label>
                        <input id="vendor_email" name="vendor_email" type="email" value="{{ old('vendor_email') }}" required autocomplete="email"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-brand-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="amount" class="block text-sm font-medium text-slate-700 mb-1">Amount</label>
                        <input id="amount" name="amount" type="number" step="0.01" value="{{ old('amount') }}" required
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
                    <div class="col-span-2">
                        <label for="file" class="block text-sm font-medium text-slate-700 mb-1">Attach the invoice <span class="text-slate-400">(optional)</span></label>
                        <input id="file" type="file" name="file"
                               class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-brand-700">
                    </div>
                </div>

                <button class="w-full bg-brand-600 hover:bg-brand-700 text-white rounded-full px-4 py-2.5 font-medium">Send invoice</button>
            </form>
        @endif
    </main>

    <footer class="border-t border-slate-100 bg-white">
        <div class="max-w-xl mx-auto px-5 py-4 text-xs text-slate-400 text-center">
            Powered by <a href="/" class="hover:text-slate-600">HandlemyAP</a> — AI accounts payable, paid via PayPal.
        </div>
    </footer>
</div>
</body>
</html>
