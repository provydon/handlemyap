# handlemyap

**AI accounts payable → PayPal.** Drop in an invoice (any format), handlemyap reads
and normalises it, applies your auto-approval rules, and pays the vendor via **PayPal
Payouts** — with a human in the loop for anything that needs a second look.

Built for the **PayPal AI Hackathon**. API- and MCP-first, so other apps (e.g. a notary
signing platform paying notaries) embed it as their payout engine.

## How it works

```
ingest (upload / API / MCP)
   → AI extracts & normalises the invoice        [the one place AI is used]
   → match/create vendor
   → deterministic rule engine                   [auto-approve or park for review]
       ├─ auto-approved → pay now / schedule for due date → PayPal Payout
       └─ needs review  → human taps Approve / Hold / Reject → PayPal Payout
```

AI does only the genuinely-semantic part (reading heterogeneous documents). Everything
that touches money — rules, duplicates, payouts — is deterministic and auditable.

## Stack
- Laravel 13 (PHP 8.3), Sanctum API tokens, Postgres (SQLite locally).
- PayPal Payouts (sandbox) via a swappable gateway (`FakePayoutGateway` for tests/local).
- Invoice extraction via Claude (`AiInvoiceExtractor`) with an offline `StubExtractor` fallback.
- Dashboard: Tailwind + AG Grid.

## Run locally
```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed       # demo user: demo@handlemyap.test / password
php artisan serve
```
With no PayPal/AI keys set it runs fully on the offline fakes. Add `PAYPAL_CLIENT_ID`,
`PAYPAL_SECRET`, and `ANTHROPIC_API_KEY` to go live.

## API (`/api/v1`, Sanctum bearer)
`POST /invoices` · `GET /invoices` · `POST /invoices/{id}/approve|hold|reject`
`GET/POST /vendors` · `GET /payouts/{id}` · `GET/PUT /rules`

`external_ref` on an invoice is the idempotency key — safe for integrators to retry.

## Tests
```bash
php artisan test
```
