<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>HandlemyAP — AI accounts payable, paid with PayPal</title>
    <meta name="description" content="Share one link, your vendors send their invoices into your queue, and HandlemyAP reads each one with AI. When you're ready, pay any invoice with PayPal in a tap — you log in to your own PayPal and the vendor gets paid.">
    @include('partials.head')
</head>
<body class="bg-white text-slate-800 antialiased">

{{-- Nav --}}
<header class="border-b border-slate-100">
    <div class="max-w-6xl mx-auto px-5 h-16 flex items-center justify-between">
        <a href="/" class="font-semibold text-lg">Handlemy<span class="text-brand-600">AP</span></a>
        <nav class="flex items-center gap-5 text-sm">
            <a href="#how" class="text-slate-600 hover:text-slate-900 hidden sm:inline">How it works</a>
            <a href="#embed" class="text-slate-600 hover:text-slate-900 hidden sm:inline">For platforms</a>
            <a href="/docs" class="text-slate-600 hover:text-slate-900 hidden sm:inline">Docs</a>
            <a href="https://github.com/provydon/handlemyap" class="text-slate-600 hover:text-slate-900">GitHub</a>
            @auth
                <a href="/app" class="bg-brand-600 hover:bg-brand-700 text-white rounded-full px-4 py-2 font-medium">Dashboard</a>
            @else
                <a href="/login" class="text-slate-600 hover:text-slate-900">Sign in</a>
                <a href="/register" class="bg-brand-600 hover:bg-brand-700 text-white rounded-full px-4 py-2 font-medium">Get started</a>
            @endauth
        </nav>
    </div>
</header>

{{-- Hero --}}
<section class="relative overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-b from-brand-50 to-white -z-10"></div>
    <div class="max-w-6xl mx-auto px-5 pt-16 pb-20 text-center">
        <span class="inline-flex items-center gap-2 rounded-full bg-brand-100 text-brand-700 text-xs font-medium px-3 py-1 mb-6">
            🟦 Built with PayPal + AI
        </span>
        <h1 class="text-4xl sm:text-5xl font-bold tracking-tight text-slate-900 max-w-3xl mx-auto leading-tight">
            Your vendors send the invoices. <span class="text-brand-600">AI reads them. You pay with PayPal.</span>
        </h1>
        <p class="mt-5 text-lg text-slate-600 max-w-2xl mx-auto">
            Share one link. Vendors send their invoices straight into your queue — any format.
            HandlemyAP reads each one with AI and lines it up ready to pay. When you're ready,
            hit <strong>Pay with PayPal</strong> — you log in to your own PayPal and the vendor gets paid.
        </p>
        <div class="mt-6 inline-flex items-center gap-2 text-sm">
            <span class="text-slate-400">Your intake link</span>
            <code class="bg-white border border-slate-200 text-brand-700 rounded-full px-3 py-1.5 shadow-sm">handlemyap&hellip;/u/<span class="text-slate-400">yourname</span></code>
        </div>
        <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
            <a href="/register" class="w-full sm:w-auto bg-brand-600 hover:bg-brand-700 text-white rounded-full px-6 py-3 font-medium shadow-sm">
                Get your link →
            </a>
            <a href="https://github.com/provydon/handlemyap"
               class="inline-flex items-center justify-center rounded-full px-6 py-3 font-medium border border-slate-300 text-slate-700 hover:bg-slate-50">
                View the code
            </a>
        </div>
    </div>
</section>

{{-- How it works --}}
<section id="how" class="max-w-6xl mx-auto px-5 py-16">
    <h2 class="text-center text-2xl font-bold text-slate-900">How it works</h2>
    <p class="text-center text-slate-500 mt-2">AI does the hard part — reading the document. You stay in control of every payment.</p>
    <div class="grid md:grid-cols-4 gap-4 mt-10">
        @foreach ([
            ['1', 'Share your link', 'Vendors submit invoices to your intake link — no login. Or add them by upload, REST API or MCP.'],
            ['2', 'AI reads them', 'Vendor, amount, currency, due date, line items — even the pay-to PayPal address. Normalised, with a confidence score, from any layout.'],
            ['3', 'Review the queue', 'Every invoice lands in one clean queue. Scan it, sort it, hold or reject anything that looks off.'],
            ['4', 'Pay with PayPal', 'Tap Pay with PayPal, approve in the PayPal popup, and the vendor is paid. You never leave the dashboard.'],
        ] as [$n, $t, $d])
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <div class="w-8 h-8 rounded-full bg-brand-600 text-white flex items-center justify-center font-semibold text-sm">{{ $n }}</div>
            <div class="font-semibold text-slate-900 mt-3">{{ $t }}</div>
            <p class="text-sm text-slate-500 mt-1">{{ $d }}</p>
        </div>
        @endforeach
    </div>
</section>

{{-- Features --}}
<section class="bg-slate-50 border-y border-slate-100">
    <div class="max-w-6xl mx-auto px-5 py-16">
        <div class="grid md:grid-cols-3 gap-5">
            @foreach ([
                ['Vendors come to you', 'Share one intake link. Vendors submit invoices straight into your queue — no account, no more chasing email or WhatsApp attachments.'],
                ['Reads any invoice', 'PDFs, scans, photos, odd layouts — the AI handles heterogeneous documents a template never could, and pulls out the pay-to PayPal address too.'],
                ['One clean queue', 'Every bill in one place with what the AI read and how sure it was. Pay, hold or reject — your call, every time.'],
                ['Pay with PayPal, in a tap', 'A PayPal popup opens right on the dashboard. You log in to your own PayPal and pay; the vendor is the payee and gets the money.'],
                ['You hold the keys', 'Nothing is ever paid automatically. Every payment is a deliberate tap and a real PayPal approval — no surprises.'],
                ['API & MCP first', 'Every action is a REST API call and an MCP tool, so other apps — and AI agents — embed HandlemyAP as their payables engine.'],
            ] as [$t, $d])
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <div class="font-semibold text-slate-900">{{ $t }}</div>
                <p class="text-sm text-slate-500 mt-2">{{ $d }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Embed / platforms --}}
<section id="embed" class="max-w-6xl mx-auto px-5 py-16">
    <div class="grid md:grid-cols-2 gap-10 items-center">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">Embed it in your app</h2>
            <p class="mt-3 text-slate-600">
                Building a marketplace or platform that pays contractors? Don’t rebuild AP.
                Submit a payable to HandlemyAP and it handles the AI extraction and lines the bill up in the queue —
                ready to pay with PayPal. A notary-signing platform paying notaries, for example, plugs straight in.
            </p>
            <ul class="mt-4 space-y-2 text-sm text-slate-600">
                <li>✓ REST API <span class="text-slate-400">and</span> an MCP server — give an AI agent the tools directly</li>
                <li>✓ Sanctum bearer tokens, per-tenant scoping</li>
                <li>✓ Idempotent via <code class="text-brand-600">external_ref</code> — safe to retry</li>
                <li>✓ Same core behind the API, the MCP server and the dashboard</li>
            </ul>
            <a href="/docs" class="inline-flex items-center mt-5 text-brand-600 font-medium hover:text-brand-700">Read the API &amp; MCP docs →</a>
        </div>
        <div class="bg-brand-900 rounded-2xl p-5 text-sm text-slate-100 overflow-x-auto">
<pre class="whitespace-pre"><span class="text-slate-400"># Submit a payable — AI reads it, it waits in the queue to be paid with PayPal</span>
curl -X POST https://handlemyap.onrender.com/api/v1/invoices \
  -H <span class="text-emerald-300">"Authorization: Bearer $TOKEN"</span> \
  -d vendor_name=<span class="text-amber-200">"Jane Q Notary"</span> \
  -d vendor_email=<span class="text-amber-200">jane@example.com</span> \
  -d amount=<span class="text-amber-200">125.00</span> -d currency=<span class="text-amber-200">USD</span> \
  -d external_ref=<span class="text-amber-200">order:12345:notary-payment</span></pre>
        </div>
    </div>
</section>

{{-- Why HandlemyAP --}}
<section class="bg-brand-900 text-white">
    <div class="max-w-6xl mx-auto px-5 py-16">
        <div class="max-w-2xl">
            <span class="inline-flex items-center gap-2 rounded-full bg-white/10 text-brand-200 text-xs font-medium px-3 py-1 mb-4">
                Why this
            </span>
            <h2 class="text-2xl sm:text-3xl font-bold">AI turns a pile of invoices into one-tap PayPal payments.</h2>
            <p class="mt-3 text-slate-300">
                Most payment AI is about checkout — money <em>in</em>. HandlemyAP is the other side: paying the
                bills. AI does the reading no template can, and PayPal does the paying — a real checkout where
                <em>you</em> log in and approve, so the vendor gets paid and you stay in control.
            </p>
        </div>
        <div class="grid md:grid-cols-3 gap-5 mt-10">
            @foreach ([
                ['Real PayPal checkout', 'Every bill is settled through PayPal Checkout — not a mock. You approve in the PayPal popup and the vendor is the payee, so the money goes to them.'],
                ['A volume multiplier, not one app', 'Because it’s embeddable over API and MCP, every app that plugs in (a notary-signing platform, a marketplace) routes its payables through it too. One integration, many apps’ volume.'],
                ['Responsible agentic payments', 'AI only reads the invoice — genuinely semantic work. A human authorises every payment with a real PayPal approval. An agent can file a bill, but it can never move the money on its own.'],
            ] as [$t, $d])
            <div class="bg-white/5 border border-white/10 rounded-2xl p-5">
                <div class="font-semibold">{{ $t }}</div>
                <p class="text-sm text-slate-300 mt-2">{{ $d }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- CTA --}}
<section class="bg-brand-600">
    <div class="max-w-6xl mx-auto px-5 py-14 text-center">
        <h2 class="text-2xl font-bold text-white">Pay your vendors with PayPal, in a tap</h2>
        <p class="text-brand-100 mt-2">Create an account, share your link, and let AI turn every invoice into a one-tap PayPal payment.</p>
        <a href="/register" class="inline-block mt-6 bg-white text-brand-700 hover:bg-brand-50 rounded-full px-6 py-3 font-medium">Get your link →</a>
    </div>
</section>

<footer class="border-t border-slate-100">
    <div class="max-w-6xl mx-auto px-5 py-8 flex flex-col sm:flex-row items-center justify-between text-sm text-slate-400 gap-2">
        <div>Handlemy<span class="text-brand-600">AP</span> — AI accounts payable, paid with PayPal</div>
        <div class="flex gap-4">
            <a href="/docs" class="hover:text-slate-600">Docs</a>
            <a href="https://github.com/provydon/handlemyap" class="hover:text-slate-600">GitHub</a>
            <a href="/login" class="hover:text-slate-600">Sign in</a>
        </div>
    </div>
</footer>

</body>
</html>
