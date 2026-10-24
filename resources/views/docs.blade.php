<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>HandlemyAP — API & MCP docs</title>
    <meta name="description" content="Embed HandlemyAP in your app: submit invoices over REST or MCP and let AI read them and your rules pay them via PayPal.">
    @include('partials.head')
</head>
<body class="bg-white text-slate-800 antialiased">

@php($base = rtrim(url('/'), '/'))

{{-- Nav --}}
<header class="border-b border-slate-100 sticky top-0 bg-white/90 backdrop-blur z-10">
    <div class="max-w-6xl mx-auto px-5 h-16 flex items-center justify-between">
        <a href="/" class="font-semibold text-lg">Handlemy<span class="text-brand-600">AP</span></a>
        <nav class="flex items-center gap-5 text-sm">
            <a href="#rest" class="text-slate-600 hover:text-slate-900 hidden sm:inline">REST API</a>
            <a href="#mcp" class="text-slate-600 hover:text-slate-900 hidden sm:inline">MCP</a>
            <a href="https://github.com/provydon/handlemyap" class="text-slate-600 hover:text-slate-900">GitHub</a>
            @auth
                <a href="/settings" class="bg-brand-600 hover:bg-brand-700 text-white rounded-full px-4 py-2 font-medium">Get a token</a>
            @else
                <a href="/register" class="bg-brand-600 hover:bg-brand-700 text-white rounded-full px-4 py-2 font-medium">Get started</a>
            @endauth
        </nav>
    </div>
</header>

<div class="max-w-6xl mx-auto px-5 py-12 grid lg:grid-cols-[200px_1fr] gap-10">
    {{-- Side nav --}}
    <aside class="hidden lg:block text-sm sticky top-24 self-start space-y-1">
        <div class="font-semibold text-slate-900 mb-2">Contents</div>
        @foreach ([
            'intro' => 'Overview',
            'auth' => 'Authentication',
            'rest' => 'REST API',
            'mcp' => 'MCP server',
            'lifecycle' => 'Invoice lifecycle',
            'idempotency' => 'Idempotency',
        ] as $anchor => $label)
            <a href="#{{ $anchor }}" class="block text-slate-500 hover:text-brand-600 py-1">{{ $label }}</a>
        @endforeach
    </aside>

    <main class="min-w-0 space-y-14">
        {{-- Overview --}}
        <section id="intro">
            <h1 class="text-3xl font-bold text-slate-900">API &amp; MCP docs</h1>
            <p class="mt-3 text-slate-600 max-w-2xl">
                HandlemyAP is accounts payable as a service. Send it an invoice and it reads the document with
                AI, applies the account owner’s auto-approval rules, and pays the vendor via <strong>PayPal Payouts</strong> —
                parking anything outside the rules for a human. Two ways to integrate:
            </p>
            <div class="grid sm:grid-cols-2 gap-4 mt-6">
                <div class="rounded-2xl border border-slate-200 p-5">
                    <div class="font-semibold text-slate-900">REST API</div>
                    <p class="text-sm text-slate-500 mt-1">Classic HTTP + JSON. Best for backends and webhooks — e.g. a platform paying its contractors.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 p-5">
                    <div class="font-semibold text-slate-900">MCP server</div>
                    <p class="text-sm text-slate-500 mt-1">Model Context Protocol. Point an AI agent (Claude, etc.) at it and it can submit and approve payables as tools.</p>
                </div>
            </div>
            <p class="mt-5 text-sm text-slate-500">
                Both surfaces call the exact same engine, so behaviour is identical. Base URL:
                <code class="bg-slate-100 rounded px-1.5 py-0.5 text-brand-700">{{ $base }}</code>
            </p>
        </section>

        {{-- Auth --}}
        <section id="auth">
            <h2 class="text-2xl font-bold text-slate-900">Authentication</h2>
            <p class="mt-3 text-slate-600 max-w-2xl">
                Create an API token in <a href="/settings" class="text-brand-600 hover:underline">Settings → API tokens</a>
                and send it as a bearer header. Every request is scoped to that token’s account.
            </p>
            <div class="bg-brand-900 rounded-2xl p-5 text-sm text-slate-100 overflow-x-auto mt-4">
<pre class="whitespace-pre">Authorization: Bearer &lt;your-token&gt;</pre>
            </div>
        </section>

        {{-- REST --}}
        <section id="rest">
            <h2 class="text-2xl font-bold text-slate-900">REST API <span class="text-slate-400 font-normal text-lg">/api/v1</span></h2>

            <div class="mt-5 overflow-x-auto">
                <table class="w-full text-sm border border-slate-200 rounded-2xl overflow-hidden">
                    <thead class="bg-slate-50 text-slate-600 text-left">
                        <tr><th class="px-4 py-2 font-medium">Method</th><th class="px-4 py-2 font-medium">Path</th><th class="px-4 py-2 font-medium">What it does</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ([
                            ['POST', '/api/v1/invoices', 'Submit a payable (JSON fields or a multipart file). Runs extraction + rules.'],
                            ['GET', '/api/v1/invoices', 'List your invoices, newest first.'],
                            ['GET', '/api/v1/invoices/{id}', 'Fetch one invoice with its vendor and payouts.'],
                            ['POST', '/api/v1/invoices/{id}/approve', 'Approve a parked invoice → pays or schedules the PayPal payout.'],
                            ['POST', '/api/v1/invoices/{id}/hold', 'Park an invoice.'],
                            ['POST', '/api/v1/invoices/{id}/reject', 'Decline an invoice.'],
                            ['GET', '/api/v1/vendors', 'List vendors.'],
                            ['GET', '/api/v1/payouts', 'List PayPal payouts and their status.'],
                            ['GET', '/api/v1/rules', 'Read your auto-approval rules.'],
                            ['PUT', '/api/v1/rules', 'Update your auto-approval rules.'],
                        ] as [$m, $p, $d])
                        <tr>
                            <td class="px-4 py-2"><span class="font-mono text-xs font-semibold {{ $m === 'GET' ? 'text-emerald-600' : 'text-brand-600' }}">{{ $m }}</span></td>
                            <td class="px-4 py-2 font-mono text-xs text-slate-700">{{ $p }}</td>
                            <td class="px-4 py-2 text-slate-500">{{ $d }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <h3 class="font-semibold text-slate-900 mt-8 mb-2">Submit an invoice</h3>
            <div class="bg-brand-900 rounded-2xl p-5 text-sm text-slate-100 overflow-x-auto">
<pre class="whitespace-pre"><span class="text-slate-400"># AI extracts, rules decide, PayPal pays</span>
curl -X POST {{ $base }}/api/v1/invoices \
  -H <span class="text-emerald-300">"Authorization: Bearer $TOKEN"</span> \
  -d vendor_name=<span class="text-amber-200">"Jane Q Notary"</span> \
  -d vendor_email=<span class="text-amber-200">jane@example.com</span> \
  -d amount=<span class="text-amber-200">125.00</span> -d currency=<span class="text-amber-200">USD</span> \
  -d external_ref=<span class="text-amber-200">order:12345:notary-payment</span></pre>
            </div>
            <p class="mt-3 text-sm text-slate-500">Response includes the invoice <code class="bg-slate-100 rounded px-1 py-0.5">status</code>
                — <code class="bg-slate-100 rounded px-1 py-0.5">paid</code>/<code class="bg-slate-100 rounded px-1 py-0.5">paying</code> if it cleared the rules,
                or <code class="bg-slate-100 rounded px-1 py-0.5">needs_review</code> with the reasons it was parked.</p>
        </section>

        {{-- MCP --}}
        <section id="mcp">
            <h2 class="text-2xl font-bold text-slate-900">MCP server</h2>
            <p class="mt-3 text-slate-600 max-w-2xl">
                HandlemyAP speaks the <strong>Model Context Protocol</strong> over streamable HTTP (JSON-RPC 2.0).
                Connect an AI agent and it gets five tools, each gated by the same deterministic rules — so an agent
                can propose and approve payments without ever being trusted with the money logic.
            </p>

            <h3 class="font-semibold text-slate-900 mt-6 mb-2">Endpoint</h3>
            <div class="bg-brand-900 rounded-2xl p-5 text-sm text-slate-100 overflow-x-auto">
<pre class="whitespace-pre">{{ $base }}/mcp      <span class="text-slate-400"># POST, JSON-RPC 2.0, Bearer auth</span></pre>
            </div>

            <h3 class="font-semibold text-slate-900 mt-6 mb-2">Connect a client</h3>
            <p class="text-sm text-slate-500 mb-2">Add it to any MCP client that supports HTTP transport, e.g.:</p>
            <div class="bg-brand-900 rounded-2xl p-5 text-sm text-slate-100 overflow-x-auto">
<pre class="whitespace-pre">{
  "mcpServers": {
    "handlemyap": {
      "type": "http",
      "url": "{{ $base }}/mcp",
      "headers": { "Authorization": "Bearer &lt;your-token&gt;" }
    }
  }
}</pre>
            </div>

            <h3 class="font-semibold text-slate-900 mt-6 mb-2">Tools</h3>
            <div class="grid sm:grid-cols-2 gap-3">
                @foreach ([
                    ['submit_invoice', 'Add a payable. AI/normalise → rules → auto-pay via PayPal or park for review.'],
                    ['list_invoices', 'See the queue, optionally filtered by status.'],
                    ['get_invoice', 'Inspect one invoice and its payout history.'],
                    ['approve_invoice', 'Release a parked invoice — the human-in-the-loop step.'],
                    ['list_payouts', 'See PayPal payouts and their settlement status.'],
                ] as [$t, $d])
                <div class="rounded-2xl border border-slate-200 p-4">
                    <code class="text-brand-600 text-sm">{{ $t }}</code>
                    <p class="text-sm text-slate-500 mt-1">{{ $d }}</p>
                </div>
                @endforeach
            </div>

            <h3 class="font-semibold text-slate-900 mt-6 mb-2">Call a tool directly</h3>
            <div class="bg-brand-900 rounded-2xl p-5 text-sm text-slate-100 overflow-x-auto">
<pre class="whitespace-pre">curl -X POST {{ $base }}/mcp \
  -H <span class="text-emerald-300">"Authorization: Bearer $TOKEN"</span> \
  -H <span class="text-emerald-300">"Content-Type: application/json"</span> \
  -d '{
    "jsonrpc": "2.0", "id": 1, "method": "tools/call",
    "params": {
      "name": "submit_invoice",
      "arguments": {
        "vendor_name": <span class="text-amber-200">"Jane Q Notary"</span>,
        "vendor_email": <span class="text-amber-200">"jane@example.com"</span>,
        "amount": <span class="text-amber-200">125.00</span>,
        "external_ref": <span class="text-amber-200">"order:12345:notary-payment"</span>
      }
    }
  }'</pre>
            </div>
        </section>

        {{-- Lifecycle --}}
        <section id="lifecycle">
            <h2 class="text-2xl font-bold text-slate-900">Invoice lifecycle</h2>
            <p class="mt-3 text-slate-600 max-w-2xl">Every invoice moves through the same states, whichever surface created it:</p>
            <div class="flex flex-wrap gap-2 mt-4 text-xs font-mono">
                @foreach (['received', 'extracting', 'needs_review', 'approved / scheduled', 'paying', 'paid', 'held', 'rejected', 'failed'] as $s)
                    <span class="bg-slate-100 text-slate-600 rounded-full px-3 py-1">{{ $s }}</span>
                @endforeach
            </div>
            <p class="mt-4 text-sm text-slate-500 max-w-2xl">
                AI only reads the document. The decision to pay is a deterministic rule engine — amount ceilings,
                known-vendors-only, daily caps, duplicate holds — so money movement is always auditable.
            </p>
        </section>

        {{-- Idempotency --}}
        <section id="idempotency">
            <h2 class="text-2xl font-bold text-slate-900">Idempotency</h2>
            <p class="mt-3 text-slate-600 max-w-2xl">
                Pass <code class="bg-slate-100 rounded px-1.5 py-0.5 text-brand-700">external_ref</code> on submit —
                your own stable key (e.g. <code class="bg-slate-100 rounded px-1 py-0.5">order:12345:payment</code>).
                Resubmitting the same reference returns the existing invoice instead of creating a duplicate, so retries
                and at-least-once webhooks are safe.
            </p>
        </section>

        <div class="border-t border-slate-100 pt-8 text-sm text-slate-400">
            Handlemy<span class="text-brand-600">AP</span> — AI accounts payable → PayPal ·
            <a href="/" class="hover:text-slate-600">Home</a> ·
            <a href="https://github.com/provydon/handlemyap" class="hover:text-slate-600">GitHub</a>
        </div>
    </main>
</div>

</body>
</html>
