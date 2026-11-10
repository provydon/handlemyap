# handlemyap — Hackathon submission

**Build What's Next with PayPal and AI**

## One-liner

An AI accounts-payable inbox: Claude reads any invoice you receive and turns it
into structured, payable data; you settle each one with **Pay with PayPal**,
paying the vendor from your own PayPal account without leaving the dashboard.

## The problem

Paying vendor invoices is manual: someone reads a PDF or a photo of an invoice,
retypes the vendor, amount and due date into a system, then goes and pays it.
handlemyap removes the retyping (AI does it) and makes the payment one click of
real PayPal checkout.

## How it meaningfully uses PayPal

The **entire payment experience is PayPal**, not a bolt-on:

- **PayPal Checkout / Orders v2** (`app/Services/Payouts/PayPalCheckout.php`) —
  creates an order with the **vendor as the `payee`**, then captures it.
- **PayPal JS SDK smart buttons** — rendered in-page so the payer approves in a
  PayPal popup and never leaves the dashboard; a full-page redirect is the
  fallback (`app/Http/Controllers/InvoicePaymentController.php`,
  `resources/views/dashboard.blade.php`).
- The payer logs in to **their own** PayPal and funds the payment from their own
  balance or card — nothing is paid from a platform balance, nothing is
  auto-paid. A successful capture records a payout and marks the invoice paid.
- Runs against the **PayPal sandbox** via one REST app's client id + secret.

## How it meaningfully uses AI

- **Claude reads every invoice** (`app/Services/Extraction/AiInvoiceExtractor.php`)
  via the Anthropic Messages API. It's **vision-capable**, so it reads PDFs,
  photos and screenshots, not just clean text.
- It returns **strict JSON**: vendor, email, invoice number, amount, currency,
  issue/due dates, PO ref, every line item, and the **payment method** — crucially,
  it extracts the **pay-to PayPal address** off the invoice so the right payee is
  pre-filled.
- Low confidence or any error routes the invoice to the human review queue
  rather than guessing — AI does the reading, humans authorise the money.
- Offline `StubExtractor` fallback means the app (and the full test suite) runs
  with no API key.

## Tools used

| Tool | How it's used |
|------|---------------|
| **PayPal** | Orders v2 (create + capture) and the PayPal JS SDK smart buttons — the whole checkout. Sandbox. |
| **Claude (Anthropic)** | Vision invoice extraction → structured JSON (the AI core). |
| **AG Grid** (sponsor) | The sortable/filterable invoice dashboard. |
| **Laravel 13 / PHP 8.3** | App framework, REST API (Sanctum), and the MCP server. |

## What judges can run

Everything runs **with no credentials** (offline AI stub + simulated PayPal
capture), so the full flow is clickable out of the box; adding an
`ANTHROPIC_API_KEY` and PayPal sandbox keys lights up the real integrations.
Setup and a 60-second walkthrough are in [README.md](README.md). A sample
invoice for the AI reader is in [`samples/sample-invoice.txt`](samples/sample-invoice.txt).

- **Web dashboard** — review queue + Pay with PayPal (`/app`).
- **Public intake link** — vendors submit invoices with no login (`/u/{handle}`).
- **REST API** — `/api/v1` (Sanctum bearer).
- **MCP server** — `/mcp`, tools: `submit_invoice`, `list_invoices`,
  `get_invoice`, `list_payouts` — so AI agents can drive payables.

## Submission checklist

- [x] Public GitHub repo with a visible MIT `LICENSE`
- [x] Meaningful PayPal integration (Checkout / Orders v2 + JS SDK)
- [x] Meaningful AI integration (Claude vision invoice extraction)
- [x] Working prototype judges can run (offline demo + real sandbox)
- [x] Setup & run instructions (README)
- [x] Tools documented (this file)
- [ ] **Demo video (< 3 min, YouTube, public)** — record this; a script is below
- [ ] Submit on the hackathon form

## Suggested demo-video script (~2.5 min)

1. **(0:00)** "Paying vendor invoices is manual — read the PDF, retype it, go
   pay it. handlemyap does both for you with AI and PayPal." Show the dashboard.
2. **(0:20)** Open the public intake link, drop in `samples/sample-invoice.txt`
   (or a PDF). Show it appear as *extracting*.
3. **(0:40)** Show the AI result in the grid — vendor, amount, currency, due
   date, line items, pay-to PayPal address — all read by Claude. Mention AG Grid.
4. **(1:10)** Hit **Pay with PayPal**. The PayPal popup opens; log in to the
   sandbox buyer and approve. "I'm paying from *my own* PayPal; the vendor is the
   payee."
5. **(1:50)** Row flips to **Paid**; show the payout record.
6. **(2:10)** "It's API- and MCP-first" — show `/docs` / one API or MCP call so
   another app or AI agent can submit a payable. Close.
