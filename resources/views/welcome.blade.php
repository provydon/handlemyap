<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>HandlemyAP — AI accounts payable, paid via PayPal</title>
    <meta name="description" content="Share one link, your vendors send their invoices into your queue, and HandlemyAP reads each one with AI, pays via PayPal under your rules, and flags irregularities — duplicates, overcharges, unfamiliar vendors — for you to approve.">
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
</head>
<body class="bg-white text-slate-800 antialiased">

{{-- Nav --}}
<header class="border-b border-slate-100">
    <div class="max-w-6xl mx-auto px-5 h-16 flex items-center justify-between">
        <div class="font-semibold text-lg">Handlemy<span class="text-indigo-600">AP</span></div>
        <nav class="flex items-center gap-5 text-sm">
            <a href="#how" class="text-slate-600 hover:text-slate-900 hidden sm:inline">How it works</a>
            <a href="#embed" class="text-slate-600 hover:text-slate-900 hidden sm:inline">For platforms</a>
            <a href="/docs" class="text-slate-600 hover:text-slate-900 hidden sm:inline">Docs</a>
            <a href="https://github.com/provydon/handlemyap" class="text-slate-600 hover:text-slate-900">GitHub</a>
            @auth
                <a href="/app" class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-4 py-2 font-medium">Dashboard</a>
            @else
                <a href="/login" class="text-slate-600 hover:text-slate-900">Sign in</a>
                <a href="/register" class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-4 py-2 font-medium">Get started</a>
            @endauth
        </nav>
    </div>
</header>

{{-- Hero --}}
<section class="relative overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-b from-indigo-50 to-white -z-10"></div>
    <div class="max-w-6xl mx-auto px-5 pt-16 pb-20 text-center">
        <span class="inline-flex items-center gap-2 rounded-full bg-indigo-100 text-indigo-700 text-xs font-medium px-3 py-1 mb-6">
            🟦 Built for the PayPal AI Hackathon
        </span>
        <h1 class="text-4xl sm:text-5xl font-bold tracking-tight text-slate-900 max-w-3xl mx-auto leading-tight">
            Your vendors send the invoices. <span class="text-indigo-600">AI helps you pay them.</span>
        </h1>
        <p class="mt-5 text-lg text-slate-600 max-w-2xl mx-auto">
            Share one link. Vendors send their invoices straight into your queue — any format.
            HandlemyAP reads each one with AI, pays via <strong>PayPal</strong> under your rules, and
            flags anything off — duplicates, overcharges, unfamiliar vendors — for you to approve.
        </p>
        <div class="mt-6 inline-flex items-center gap-2 text-sm">
            <span class="text-slate-400">Your intake link</span>
            <code class="bg-white border border-slate-200 text-indigo-700 rounded-lg px-3 py-1.5 shadow-sm">handlemyap&hellip;/u/<span class="text-slate-400">yourname</span></code>
        </div>
        <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
            <a href="/register" class="w-full sm:w-auto bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-6 py-3 font-medium shadow-sm">
                Get your link →
            </a>
            <a href="https://github.com/provydon/handlemyap"
               class="inline-flex items-center justify-center rounded-lg px-6 py-3 font-medium border border-slate-300 text-slate-700 hover:bg-slate-50">
                View the code
            </a>
        </div>
    </div>
</section>

{{-- How it works --}}
<section id="how" class="max-w-6xl mx-auto px-5 py-16">
    <h2 class="text-center text-2xl font-bold text-slate-900">How it works</h2>
    <p class="text-center text-slate-500 mt-2">AI only does the hard part — reading the document. Everything that touches money is deterministic.</p>
    <div class="grid md:grid-cols-4 gap-4 mt-10">
        @foreach ([
            ['1', 'Share your link', 'Vendors submit invoices to your intake link — no login. Or forward uploads, API and MCP.'],
            ['2', 'AI reads them', 'Vendor, amount, due date, line items — normalised, with a confidence score. Any layout.'],
            ['3', 'Rules decide', 'Auto-approve within your limits, or park it in the review queue.'],
            ['4', 'PayPal pays', 'A PayPal Payout goes to the vendor by email. Scheduled for the due date.'],
        ] as [$n, $t, $d])
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <div class="w-8 h-8 rounded-full bg-indigo-600 text-white flex items-center justify-center font-semibold text-sm">{{ $n }}</div>
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
                ['Reads any invoice', 'PDFs, scans, photos, odd layouts — the AI handles heterogeneous documents a template never could.'],
                ['Flags what looks off', 'Duplicate bills, amounts over your limit, unfamiliar vendors — parked in one clean queue with the exact reason it needs your eyes.'],
                ['An autonomy dial', 'You set the rules: pay under $X automatically, known vendors only, cap the daily spend. Everything else waits for a tap.'],
                ['Pays anyone', 'PayPal Payouts reach vendors by email — they don’t need an existing PayPal setup to get paid.'],
                ['API & MCP first', 'Every action is an API call and an MCP tool, so other apps embed HandlemyAP as their payout engine.'],
            ] as [$t, $d])
            <div class="bg-white rounded-xl border border-slate-200 p-5">
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
                Submit a payable to HandlemyAP and it handles extraction, rules, approval and the PayPal payout —
                then calls you back. A notary-signing platform paying notaries, for example, plugs in as a payout rail.
            </p>
            <ul class="mt-4 space-y-2 text-sm text-slate-600">
                <li>✓ REST API <span class="text-slate-400">and</span> an MCP server — give an AI agent the tools directly</li>
                <li>✓ Sanctum bearer tokens, per-tenant scoping</li>
                <li>✓ Idempotent via <code class="text-indigo-600">external_ref</code> — safe to retry</li>
                <li>✓ Same core behind the API, the MCP server and the dashboard</li>
            </ul>
            <a href="/docs" class="inline-flex items-center mt-5 text-indigo-600 font-medium hover:text-indigo-700">Read the API &amp; MCP docs →</a>
        </div>
        <div class="bg-slate-900 rounded-xl p-5 text-sm text-slate-100 overflow-x-auto">
<pre class="whitespace-pre"><span class="text-slate-400"># Submit a payable — AI extracts, rules decide, PayPal pays</span>
curl -X POST https://handlemyap.onrender.com/api/v1/invoices \
  -H <span class="text-emerald-300">"Authorization: Bearer $TOKEN"</span> \
  -d vendor_name=<span class="text-amber-200">"Jane Q Notary"</span> \
  -d vendor_email=<span class="text-amber-200">jane@example.com</span> \
  -d amount=<span class="text-amber-200">125.00</span> -d currency=<span class="text-amber-200">USD</span> \
  -d external_ref=<span class="text-amber-200">order:12345:notary-payment</span></pre>
        </div>
    </div>
</section>

{{-- Why HandlemyAP + PayPal --}}
<section class="bg-slate-900 text-white">
    <div class="max-w-6xl mx-auto px-5 py-16">
        <div class="max-w-2xl">
            <span class="inline-flex items-center gap-2 rounded-full bg-white/10 text-indigo-200 text-xs font-medium px-3 py-1 mb-4">
                Why this, and why PayPal
            </span>
            <h2 class="text-2xl sm:text-3xl font-bold">A payables layer that turns bills into PayPal volume.</h2>
            <p class="mt-3 text-slate-300">
                Most payment AI is about checkout — money <em>in</em>. HandlemyAP is the other side: money
                <em>out</em>. It’s accounts payable packaged as a service other apps absorb, and every
                invoice it clears settles on PayPal Payouts.
            </p>
        </div>
        <div class="grid md:grid-cols-3 gap-5 mt-10">
            @foreach ([
                ['PayPal is the rail — by design', 'Approval rules exist to gate a PayPal payout. Remove the rail and there’s no product. Payouts reach any vendor by email — no existing PayPal setup required.'],
                ['A volume multiplier, not one app', 'Because it’s embeddable over API and MCP, every app that plugs in (a notary-signing platform, a marketplace) routes its payouts through PayPal too. One integration, many apps’ volume.'],
                ['Responsible agentic payments', 'AI only reads the invoice — genuinely semantic work. A deterministic rule engine decides what pays, with a human in the loop for the rest. An agent can act, never run off with the money.'],
            ] as [$t, $d])
            <div class="bg-white/5 border border-white/10 rounded-xl p-5">
                <div class="font-semibold">{{ $t }}</div>
                <p class="text-sm text-slate-300 mt-2">{{ $d }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- CTA --}}
<section class="bg-indigo-600">
    <div class="max-w-6xl mx-auto px-5 py-14 text-center">
        <h2 class="text-2xl font-bold text-white">Put your payables on autopilot</h2>
        <p class="text-indigo-100 mt-2">Create an account, share your link, and let the invoices pay themselves via PayPal.</p>
        <a href="/register" class="inline-block mt-6 bg-white text-indigo-700 hover:bg-indigo-50 rounded-lg px-6 py-3 font-medium">Get your link →</a>
    </div>
</section>

<footer class="border-t border-slate-100">
    <div class="max-w-6xl mx-auto px-5 py-8 flex flex-col sm:flex-row items-center justify-between text-sm text-slate-400 gap-2">
        <div>Handlemy<span class="text-indigo-600">AP</span> — AI accounts payable → PayPal</div>
        <div class="flex gap-4">
            <a href="/docs" class="hover:text-slate-600">Docs</a>
            <a href="https://github.com/provydon/handlemyap" class="hover:text-slate-600">GitHub</a>
            <a href="/login" class="hover:text-slate-600">Sign in</a>
        </div>
    </div>
</footer>

</body>
</html>
