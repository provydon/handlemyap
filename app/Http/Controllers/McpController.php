<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payout;
use App\Models\User;
use App\Services\Payables\PayablesOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

/**
 * HandlemyAP MCP server — a self-contained JSON-RPC 2.0 endpoint speaking the
 * Model Context Protocol over streamable HTTP (POST /mcp).
 *
 * Every tool is a thin caller of PayablesOrchestrator — the exact same core the
 * REST API and the dashboard use — so an AI agent gets identical, rule-gated
 * behaviour: AI reads the invoice, a deterministic rule engine decides auto-pay
 * vs. human review, and PayPal Payouts move the money.
 *
 * Auth: the same Sanctum bearer tokens issued in Settings. Every call is scoped
 * to the token's user, so an agent can only ever see or act on its own payables.
 */
class McpController extends Controller
{
    private const PROTOCOL_VERSION = '2025-06-18';

    public function handle(Request $request, PayablesOrchestrator $orchestrator): JsonResponse
    {
        $body = $request->json()->all();

        // JSON-RPC notifications (e.g. notifications/initialized) carry no id and
        // expect no response body.
        $id = $body['id'] ?? null;
        $method = $body['method'] ?? null;
        $params = $body['params'] ?? [];

        if ($method === null) {
            return $this->error($id, -32600, 'Invalid Request');
        }

        // initialize is the handshake and needs no auth. Everything else does.
        if ($method === 'initialize') {
            return $this->result($id, $this->initialize());
        }

        if (str_starts_with($method, 'notifications/')) {
            return response()->json(null, 202);
        }

        if ($method === 'ping') {
            return $this->result($id, (object) []);
        }

        $user = $this->authenticate($request);
        if (! $user) {
            return $this->error($id, -32001, 'Unauthorized — pass a HandlemyAP API token as a Bearer header. Create one in Settings.');
        }

        return match ($method) {
            'tools/list' => $this->result($id, ['tools' => $this->tools()]),
            'tools/call' => $this->callTool($id, $user, $params, $orchestrator),
            default => $this->error($id, -32601, "Method not found: {$method}"),
        };
    }

    /** A browser-friendly description of the endpoint (GET /mcp). */
    public function info(): JsonResponse
    {
        return response()->json([
            'name' => 'handlemyap',
            'description' => 'HandlemyAP MCP server — submit invoices, let AI read them and your rules pay them via PayPal.',
            'transport' => 'streamable-http (JSON-RPC 2.0 over POST)',
            'endpoint' => url('/mcp'),
            'auth' => 'Authorization: Bearer <HandlemyAP API token>  (create one in Settings)',
            'protocolVersion' => self::PROTOCOL_VERSION,
            'tools' => array_map(fn ($t) => $t['name'], $this->tools()),
            'docs' => url('/docs'),
        ]);
    }

    // ---- protocol handlers --------------------------------------------------

    private function initialize(): array
    {
        return [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => ['tools' => ['listChanged' => false]],
            'serverInfo' => [
                'name' => 'handlemyap',
                'title' => 'HandlemyAP — AI accounts payable, paid via PayPal',
                'version' => '1.0.0',
            ],
            'instructions' => 'Submit vendor invoices and HandlemyAP reads them with AI, applies the '
                .'account owner\'s auto-approval rules, and pays the vendor via PayPal Payouts. '
                .'Anything outside the rules is parked for a human. Use submit_invoice to add a '
                .'payable, list_invoices to see the queue, and approve_invoice to release a parked one.',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function tools(): array
    {
        return [
            [
                'name' => 'submit_invoice',
                'description' => 'Submit a vendor invoice as a payable. AI/normalisation runs, then the owner\'s '
                    .'rules decide: it is either auto-approved and paid via PayPal, or parked for human review. '
                    .'Pass external_ref (an idempotency key) to make retries safe.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'vendor_name' => ['type' => 'string', 'description' => 'The vendor / payee name.'],
                        'vendor_email' => ['type' => 'string', 'description' => 'Vendor email — the PayPal payout address.'],
                        'amount' => ['type' => 'number', 'description' => 'Invoice total.'],
                        'currency' => ['type' => 'string', 'description' => '3-letter ISO currency (default USD).'],
                        'invoice_number' => ['type' => 'string', 'description' => 'The vendor\'s invoice number.'],
                        'due_date' => ['type' => 'string', 'description' => 'Due date (YYYY-MM-DD). If future, payment is scheduled.'],
                        'external_ref' => ['type' => 'string', 'description' => 'Your idempotency key, e.g. order:12345:payment.'],
                    ],
                    'required' => ['vendor_name', 'amount'],
                ],
            ],
            [
                'name' => 'list_invoices',
                'description' => 'List payables in the queue, newest first. Optionally filter by status '
                    .'(received, needs_review, scheduled, paying, paid, held, rejected, failed).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => ['type' => 'string', 'description' => 'Filter by lifecycle status.'],
                        'limit' => ['type' => 'integer', 'description' => 'Max rows (default 25, max 100).'],
                    ],
                ],
            ],
            [
                'name' => 'get_invoice',
                'description' => 'Get one invoice by id, including its payout history.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['id' => ['type' => 'string', 'description' => 'The invoice id.']],
                    'required' => ['id'],
                ],
            ],
            [
                'name' => 'approve_invoice',
                'description' => 'Approve a parked (needs_review) invoice. Releases the PayPal payout now, or '
                    .'schedules it for the due date. Use this for a human-in-the-loop approval step.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['id' => ['type' => 'string', 'description' => 'The invoice id to approve.']],
                    'required' => ['id'],
                ],
            ],
            [
                'name' => 'list_payouts',
                'description' => 'List PayPal payouts, newest first, with their settlement status.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['limit' => ['type' => 'integer', 'description' => 'Max rows (default 25, max 100).']],
                ],
            ],
        ];
    }

    private function callTool(mixed $id, User $user, array $params, PayablesOrchestrator $orchestrator): JsonResponse
    {
        $name = $params['name'] ?? '';
        $args = $params['arguments'] ?? [];

        try {
            $payload = match ($name) {
                'submit_invoice' => $this->submitInvoice($user, $args, $orchestrator),
                'list_invoices' => $this->listInvoices($user, $args),
                'get_invoice' => $this->getInvoice($user, $args),
                'approve_invoice' => $this->approveInvoice($user, $args, $orchestrator),
                'list_payouts' => $this->listPayouts($user, $args),
                default => throw new \InvalidArgumentException("Unknown tool: {$name}"),
            };
        } catch (Throwable $e) {
            // Tool errors are returned inside the result (isError) so the agent can read them.
            return $this->result($id, [
                'content' => [['type' => 'text', 'text' => $e->getMessage()]],
                'isError' => true,
            ]);
        }

        return $this->result($id, [
            'content' => [['type' => 'text', 'text' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)]],
            'structuredContent' => $payload,
            'isError' => false,
        ]);
    }

    // ---- tool implementations ----------------------------------------------

    private function submitInvoice(User $user, array $args, PayablesOrchestrator $orchestrator): array
    {
        if (blank($args['vendor_name'] ?? null)) {
            throw new \InvalidArgumentException('vendor_name is required.');
        }
        if (! is_numeric($args['amount'] ?? null)) {
            throw new \InvalidArgumentException('amount is required and must be a number.');
        }

        $invoice = $orchestrator->ingestData($user, [
            'source' => 'mcp',
            'vendor_name' => $args['vendor_name'],
            'vendor_email' => $args['vendor_email'] ?? null,
            'amount' => (float) $args['amount'],
            'currency' => strtoupper($args['currency'] ?? 'USD'),
            'invoice_number' => $args['invoice_number'] ?? null,
            'due_date' => $args['due_date'] ?? null,
            'external_ref' => $args['external_ref'] ?? null,
        ]);

        return $this->summarize($invoice);
    }

    private function listInvoices(User $user, array $args): array
    {
        $query = $user->invoices()->with('vendor')->latest();

        if (filled($args['status'] ?? null)) {
            $query->where('status', $args['status']);
        }

        $limit = min(max((int) ($args['limit'] ?? 25), 1), 100);

        return [
            'invoices' => $query->limit($limit)->get()->map(fn (Invoice $i) => $this->summarize($i))->all(),
        ];
    }

    private function getInvoice(User $user, array $args): array
    {
        $invoice = $user->invoices()->with(['vendor', 'payouts'])->findOrFail($args['id'] ?? '');

        return $this->summarize($invoice, withPayouts: true);
    }

    private function approveInvoice(User $user, array $args, PayablesOrchestrator $orchestrator): array
    {
        $invoice = $user->invoices()->findOrFail($args['id'] ?? '');
        $orchestrator->approve($invoice, $user);

        return $this->summarize($invoice->refresh()->load(['vendor', 'payouts']), withPayouts: true);
    }

    private function listPayouts(User $user, array $args): array
    {
        $limit = min(max((int) ($args['limit'] ?? 25), 1), 100);

        $payouts = $user->payouts()->with('invoice')->latest()->limit($limit)->get()
            ->map(fn (Payout $p) => [
                'id' => $p->id,
                'invoice_id' => $p->invoice_id,
                'vendor' => $p->invoice?->vendor_name,
                'amount' => $p->amount,
                'currency' => $p->currency,
                'status' => $p->status,
                'paypal_batch_id' => $p->paypal_batch_id,
                'paid_at' => $p->paid_at?->toIso8601String(),
            ])->all();

        return ['payouts' => $payouts];
    }

    // ---- shaping ------------------------------------------------------------

    /** A compact, agent-friendly view of an invoice with a plain-English decision line. */
    private function summarize(Invoice $invoice, bool $withPayouts = false): array
    {
        $out = [
            'id' => $invoice->id,
            'status' => $invoice->status,
            'decision' => $this->decision($invoice),
            'vendor_name' => $invoice->vendor_name,
            'vendor_email' => $invoice->vendor?->email,
            'amount' => $invoice->amount,
            'currency' => $invoice->currency,
            'invoice_number' => $invoice->invoice_number,
            'due_date' => $invoice->due_date?->toDateString(),
            'external_ref' => $invoice->external_ref,
            'review_reasons' => $invoice->review_reasons,
        ];

        if ($withPayouts) {
            $out['payouts'] = $invoice->payouts->map(fn (Payout $p) => [
                'id' => $p->id,
                'status' => $p->status,
                'amount' => $p->amount,
                'currency' => $p->currency,
                'paypal_batch_id' => $p->paypal_batch_id,
            ])->all();
        }

        return $out;
    }

    private function decision(Invoice $invoice): string
    {
        $reasons = implode('; ', $invoice->review_reasons ?? []);

        return match ($invoice->status) {
            Invoice::STATUS_PAID => 'Auto-approved and paid via PayPal.',
            Invoice::STATUS_PAYING => 'Auto-approved — PayPal payout in progress.',
            Invoice::STATUS_SCHEDULED => 'Approved — scheduled to pay via PayPal on the due date.',
            Invoice::STATUS_NEEDS_REVIEW => 'Parked for human review'.($reasons ? ": {$reasons}" : '.'),
            Invoice::STATUS_HELD => 'Held by a human.',
            Invoice::STATUS_REJECTED => 'Rejected by a human.',
            Invoice::STATUS_FAILED => 'Payout failed — see the invoice for details.',
            default => 'Received.',
        };
    }

    // ---- JSON-RPC + auth plumbing -------------------------------------------

    private function authenticate(Request $request): ?User
    {
        $bearer = $request->bearerToken();
        if (! $bearer) {
            return null;
        }

        $token = PersonalAccessToken::findToken($bearer);

        return $token?->tokenable instanceof User ? $token->tokenable : null;
    }

    private function result(mixed $id, mixed $result): JsonResponse
    {
        return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
    }

    private function error(mixed $id, int $code, string $message): JsonResponse
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => ['code' => $code, 'message' => $message],
        ]);
    }
}
