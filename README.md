# handlemyap

**AI reads your invoices. You pay them with PayPal in one tap.**

🔗 **Live demo: https://handlemyap.com** — sign in with `demo@handlemyap.test` / `password`

handlemyap is an accounts-payable inbox. Vendors send invoices to your link in
any format — PDF, a photo, a screenshot, plain text. Claude reads each one,
normalises it (vendor, amount, currency, due date, line items, and even the
pay-to PayPal address), matches it to a vendor, and drops it into a review
queue. When you're ready, you hit **Pay with PayPal** — a PayPal popup opens,
**you log in to your own PayPal and pay the vendor from your own balance or
card**, without leaving the dashboard.

Built for the **Build What's Next with PayPal and AI** hackathon. It uses both
required technologies as the core of the product:

- **PayPal** — the entire payment experience is PayPal Checkout (Orders v2 +
  the PayPal JS SDK smart buttons). The vendor is the `payee`; the payer funds
  it from their own PayPal account.
- **AI** — every invoice is read by **Claude** (Anthropic Messages API,
  vision-capable), turning heterogeneous documents into structured, payable
  data. This is the thing that would otherwise be manual data entry.
- **AG Grid** (sponsor tool) powers the sortable, filterable invoice dashboard.

## How it works

```
  invoice arrives ──►  Claude reads & normalises it  ──►  match / create vendor
   (link · upload           [the AI step: PDF/image/text          │
    · REST API · MCP)         → strict JSON invoice]               ▼
                                                            "Needs review" queue
                                                                   │
                                                   you tap  Pay with PayPal
                                                                   │
                                           PayPal popup — YOU log in & pay the
                                           vendor (vendor = payee) from YOUR
                                           own PayPal balance / card
                                                                   │
                                                                   ▼
                                                  capture recorded · invoice Paid
```

AI does the genuinely-semantic part (reading any document). The money movement
is an explicit, human-initiated PayPal checkout — nothing is auto-paid, and
nothing is funded from a platform balance.

## Why it's useful

It's **API- and MCP-first**, so another product can embed it as its payables
engine. Example: a notary-signing platform `POST`s a payable to the REST API
(or an AI agent calls the MCP `submit_invoice` tool); handlemyap reads it,
queues it, and a human pays the notary with PayPal.

## Stack

- Laravel 13 (PHP 8.3), SQLite, Sanctum API tokens.
- PayPal Checkout — `PayPalCheckout` (Orders v2) + in-page PayPal JS SDK smart
  buttons, with a full-page redirect fallback.
- Invoice extraction — `AiInvoiceExtractor` (Claude, vision) with an offline
  `StubExtractor` fallback so the app runs with zero keys.
- Dashboard — Blade + Tailwind + **AG Grid**.
- An MCP server (`/mcp`) and a REST API (`/api/v1`) over the same core.

## Run it locally

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed      # seeds a demo workspace
php artisan serve               # http://127.0.0.1:8000
```

Log in with the seeded demo account:

```
email:    demo@handlemyap.test
password: password
```

**It runs with no keys at all.** With no credentials it uses the offline AI
stub and simulates the PayPal capture, so judges can click the whole flow
end-to-end. To light it up for real, add these to `.env`:

```dotenv
ANTHROPIC_API_KEY=sk-ant-...      # turns on the real Claude invoice reader
PAYPAL_CLIENT_ID=...              # a PayPal REST app (sandbox is fine)
PAYPAL_SECRET=...
PAYPAL_ENV=sandbox                # sandbox | live
```

To force the fully-offline demo even when keys are present, set
`HANDLEMYAP_GATEWAY=fake`.

### Try it in 60 seconds

1. Open `http://127.0.0.1:8000/app` and sign in as the demo user.
2. You'll see seeded invoices in the AG Grid. Each has an **Actions** menu:
   **Pay with PayPal / Hold / Reject**.
3. Click **Pay with PayPal** → the PayPal popup opens → pay → the row flips to
   **Paid** (simulated in demo mode, a real sandbox capture with PayPal keys).
4. To watch an invoice get read, submit one through the public intake link on
   your **Settings** page (`/u/{your-handle}`) or upload it from the dashboard.
   Use `samples/sample-invoice.json` for a clean read with no API key (the
   offline stub), or `samples/sample-invoice.txt` (or your own PDF/photo) to
   exercise the real Claude reader once `ANTHROPIC_API_KEY` is set.

## REST API (`/api/v1`, Sanctum bearer)

Create a token at **Settings → API** in the dashboard, then:

```
GET  /api/v1/me
GET  /api/v1/invoices                 list
POST /api/v1/invoices                 submit (JSON fields or a file upload)
GET  /api/v1/invoices/{id}            show
POST /api/v1/invoices/{id}/extract    run / re-run the AI read
POST /api/v1/invoices/{id}/hold       park it
POST /api/v1/invoices/{id}/reject     decline it
GET/POST /api/v1/vendors              list / upsert
GET  /api/v1/payouts, /payouts/{id}   payment records
```

`external_ref` on an invoice is the **idempotency key** — safe for integrators
to retry.

## MCP server (`/mcp`)

A JSON-RPC MCP endpoint so AI agents can drive payables directly. Authenticate
with the same Sanctum bearer token. Tools:

- `submit_invoice` — add a payable (idempotent on `external_ref`)
- `list_invoices` — the current queue
- `get_invoice` — one invoice with its status
- `list_payouts` — completed PayPal payments

See `/docs` in the running app for live examples.

## Tests

```bash
php artisan test        # 42 passing
```

The suite runs fully offline (fake gateway + stub extractor), covering ingest →
review, the PayPal order/capture endpoints, idempotency, auth isolation, the
REST API, and the MCP server.

## License

MIT — see [LICENSE](LICENSE).
