# QuickBooks Online (QBO) integration — plan

**Goal:** keep the user's books in sync automatically. When an invoice is approved
in HandlemyAP it appears in QBO as a **Bill**; when the PayPal payout settles, the
bill is marked paid via a **Bill Payment** — the same pattern NotaryDash uses.

Design it **provider-agnostic** from day one (an `AccountingSync` contract) so **Xero**
plugs in later with no rework — mirroring how NotaryDash abstracts payout providers.

---

## 1. The sync model

| HandlemyAP event | QBO object created |
|---|---|
| Vendor first referenced | **Vendor** (create if missing; store `qbo_vendor_id`) |
| Invoice approved | **Bill** (the payable) — stores `qbo_bill_id` |
| Payout settled → Paid | **BillPayment** (clears the bill) — stores `qbo_payment_id` |

One-way push (HandlemyAP → QBO) is enough for v1. Two-way (read QBO bills in) is a later option.

## 2. Data model additions
- **`accounting_connections`** — `user_id`, `provider` ('qbo'), `realm_id`,
  `access_token` (encrypted), `refresh_token` (encrypted), `expires_at`, `connected_at`.
- **`vendors`** — add `qbo_vendor_id` (nullable). (Or a polymorphic `accounting_vendor_map`
  for multi-provider.)
- **`invoices`** — add `qbo_bill_id`, `qbo_payment_id`, `sync_status`
  (pending/synced/failed), `synced_at`.

## 3. The abstraction (so Xero is free later)
```
interface AccountingSync {
    public function ensureVendor(Vendor $vendor): string;        // returns provider vendor id
    public function pushBill(Invoice $invoice): string;          // returns provider bill id
    public function markBillPaid(Invoice $invoice): string;      // returns provider payment id
}
```
`QboSync implements AccountingSync`, resolved per-user from their `accounting_connections` row.
Token refresh is handled transparently inside the client.

## 4. Auth (Intuit OAuth2)
- **Scopes:** `com.intuit.quickbooks.accounting`.
- Standard OAuth2 code flow: "Connect QuickBooks" → Intuit consent → callback stores
  `realm_id` + tokens.
- Access token ~1h, refresh token ~100 days → a token manager refreshes on 401/expiry and
  persists the rotated refresh token.
- **API base:** sandbox `https://sandbox-quickbooks.api.intuit.com`, prod
  `https://quickbooks.api.intuit.com`; endpoints `/v3/company/{realmId}/{vendor|bill|billpayment|query}`.
- Use the official `quickbooks/v3-php-sdk` for OAuth + token refresh (robust), or a thin
  `Http` client in the same self-contained style as `PayPalPayoutGateway`.

## 5. Where it hooks into the existing engine
- `PayablesOrchestrator::approve()` → dispatch **`SyncBillToQbo`** job → `ensureVendor` + `pushBill`.
- `PayoutReconciler` / `pay()` on **Paid** → dispatch **`MarkBillPaidInQbo`** job → `markBillPaid`.
- Jobs are queued, retried on failure, and set `invoices.sync_status`; the dashboard shows a
  small "Synced to QBO ✓ / retry" badge.

## 6. Settings UI
- "Connect QuickBooks" button (starts OAuth), connection status (company name), Disconnect.
- Per-invoice sync status + a manual "Re-sync" action.

## 7. Edge cases to handle
- **Vendor dedup** — match existing QBO vendors by email/name before creating.
- **Idempotency** — stamp our invoice id into the QBO Bill's `DocNumber`/private note and
  check before create, so retries don't double-post.
- **Currency** — QBO rejects non-home currency unless *multicurrency* is enabled; start
  home-currency-only, detect and surface the limitation.
- **Token expiry / disconnect** — refresh transparently; on hard failure mark the connection
  `needs_reconnect` and prompt in Settings.
- **Partial failures** — `sync_status = failed`, retained for retry, visible in the UI.

## 8. Phasing
- **A.** OAuth connect + store tokens + "Connected" status.
- **B.** `ensureVendor` + `pushBill` on approval.
- **C.** `markBillPaid` on settlement + sync badges in the dashboard.
- **D.** Xero via the same `AccountingSync` contract.

**Rough effort:** ~1–2 weeks for QBO end-to-end (OAuth, bills, payments, jobs, UI). Not
hackathon-scoped (accounting sync, not PayPal/AI core) — a post-hackathon product phase.

## 9. Security
- Encrypt tokens (`encrypted` cast); never log them; request minimal scope; store `realm_id`
  per connection; all sync runs server-side in queued jobs.
