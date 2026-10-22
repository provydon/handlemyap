<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>handlemyap — AI accounts payable, paid via PayPal</title>
    <meta name="description" content="handlemyap reads any invoice with AI, applies your rules, and pays the vendor via PayPal — with a human in the loop for anything that needs a second look.">
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
</head>
<body class="bg-white text-slate-800 antialiased">

{{-- Nav --}}
<header class="border-b border-slate-100">
    <div class="max-w-6xl mx-auto px-5 h-16 flex items-center justify-between">
        <div class="font-semibold text-lg">handle<span class="text-indigo-600">my</span>ap</div>
        <nav class="flex items-center gap-5 text-sm">
            <a href="#how" class="text-slate-600 hover:text-slate-900 hidden sm:inline">How it works</a>
            <a href="#embed" class="text-slate-600 hover:text-slate-900 hidden sm:inline">For platforms</a>
            <a href="https://github.com/provydon/handlemyap" class="text-slate-600 hover:text-slate-900">GitHub</a>
            @auth
                <a href="/app" class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-4 py-2 font-medium">Dashboard</a>
            @else
                <a href="/login" class="text-slate-600 hover:text-slate-900">Sign in</a>
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
            AI reads your invoices <span class="text-indigo-600">and pays them.</span>
        </h1>
        <p class="mt-5 text-lg text-slate-600 max-w-2xl mx-auto">
            Drop in an invoice — any format. handlemyap extracts it with AI, applies your rules,
            and pays the vendor via <strong>PayPal</strong>. A human taps approve on anything that needs a second look.
        </p>
        <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
            <form method="POST" action="/demo-login">
                @csrf
                <button class="w-full sm:w-auto bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-6 py-3 font-medium shadow-sm">
                    Try the live demo →
                </button>
            </form>
            <a href="https://github.com/provydon/handlemyap"
               class="inline-flex items-center justify-center rounded-lg px-6 py-3 font-medium border border-slate-300 text-slate-700 hover:bg-slate-50">
                View the code
            </a>
        </div>
        <p class="mt-3 text-xs text-slate-400">No sign-up — one click opens a seeded demo account.</p>
    </div>
</section>

{{-- How it works --}}
<section id="how" class="max-w-6xl mx-auto px-5 py-16">
    <h2 class="text-center text-2xl font-bold text-slate-900">How it works</h2>
    <p class="text-center text-slate-500 mt-2">AI only does the hard part — reading the document. Everything that touches money is deterministic.</p>
    <div class="grid md:grid-cols-4 gap-4 mt-10">
        @foreach ([
            ['1', 'Ingest', 'Upload, email, API or MCP — any invoice, any layout.'],
            ['2', 'AI reads it', 'Vendor, amount, due date, line items — normalised, with a confidence score.'],
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
                ['Reads any invoice', 'PDFs, scans, photos, odd layouts — the AI handles heterogeneous documents a template never could.'],
                ['An autonomy dial', 'You set the rules: pay under $X automatically, known vendors only, cap the daily spend. Everything else waits for a tap.'],
                ['Human in the loop', 'Approve, hold or reject from one clean queue — with the exact reason an invoice needs your eyes.'],
                ['Catches duplicates', 'Deterministic duplicate detection stops the classic AP mistake: paying the same bill twice.'],
                ['Pays anyone', 'PayPal Payouts reach vendors by email — they don’t need an existing PayPal setup to get paid.'],
                ['API & MCP first', 'Every action is an API call and an MCP tool, so other apps embed handlemyap as their payout engine.'],
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
                Submit a payable to handlemyap and it handles extraction, rules, approval and the PayPal payout —
                then calls you back. A notary-signing platform paying notaries, for example, plugs in as a payout rail.
            </p>
            <ul class="mt-4 space-y-2 text-sm text-slate-600">
                <li>✓ Sanctum bearer tokens, per-tenant scoping</li>
                <li>✓ Idempotent via <code class="text-indigo-600">external_ref</code> — safe to retry</li>
                <li>✓ Same core behind the API, the MCP server and the dashboard</li>
            </ul>
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

{{-- CTA --}}
<section class="bg-indigo-600">
    <div class="max-w-6xl mx-auto px-5 py-14 text-center">
        <h2 class="text-2xl font-bold text-white">See it pay an invoice</h2>
        <p class="text-indigo-100 mt-2">Open the seeded demo — add an invoice and watch it flow to a PayPal payout.</p>
        <form method="POST" action="/demo-login" class="mt-6">
            @csrf
            <button class="bg-white text-indigo-700 hover:bg-indigo-50 rounded-lg px-6 py-3 font-medium">Try the live demo →</button>
        </form>
    </div>
</section>

<footer class="border-t border-slate-100">
    <div class="max-w-6xl mx-auto px-5 py-8 flex flex-col sm:row items-center justify-between text-sm text-slate-400 gap-2">
        <div>handle<span class="text-indigo-600">my</span>ap — AI accounts payable → PayPal</div>
        <div class="flex gap-4">
            <a href="https://github.com/provydon/handlemyap" class="hover:text-slate-600">GitHub</a>
            <a href="/login" class="hover:text-slate-600">Sign in</a>
        </div>
    </div>
</footer>

</body>
</html>
