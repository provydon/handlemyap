<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ExtractInvoice;
use App\Services\Payables\PayablesOrchestrator;
use Illuminate\Http\Request;

/**
 * Invoices API. Every query is scoped to the authenticated token's user, so an
 * integrator (e.g. NotaryDash) can only ever see or act on its own payables.
 */
class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->invoices()
            ->with('vendor')->latest()->limit(200)->get();
    }

    public function show(Request $request, string $invoice)
    {
        return $request->user()->invoices()
            ->with(['vendor', 'payouts'])->findOrFail($invoice);
    }

    /**
     * Submit an invoice — either a file (multipart `file`) for AI extraction,
     * or already-structured JSON fields. `external_ref` is the idempotency key.
     */
    public function store(Request $request, PayablesOrchestrator $orchestrator)
    {
        // Where the payable came from. Defaults to 'api'; the dashboard, which
        // dogfoods this same endpoint, labels its own uploads/manual entries.
        $source = in_array($request->input('source'), ['intake', 'upload', 'api', 'mcp', 'manual'], true)
            ? $request->input('source')
            : 'api';

        if ($request->hasFile('file')) {
            $request->validate(['file' => 'required|file|max:10240']);

            $meta = [
                'source' => $source,
                'external_ref' => $request->input('external_ref'),
                'metadata' => $request->input('metadata'),
            ];

            // `defer` stores the file now, returns straight away (status
            // "extracting"), and queues the AI read as a background job — this
            // is how the dashboard gives an instant upload confirmation.
            if ($request->boolean('defer')) {
                $invoice = $orchestrator->receiveUpload($request->user(), $request->file('file'), $meta);
                ExtractInvoice::dispatch($invoice->id);
            } else {
                $invoice = $orchestrator->ingestUpload($request->user(), $request->file('file'), $meta);
            }
        } else {
            $data = $request->validate([
                'vendor_name' => 'nullable|string|max:255',
                'vendor_email' => 'nullable|email|max:255',
                'invoice_number' => 'nullable|string|max:255',
                'amount' => 'nullable|numeric|min:0',
                'currency' => 'nullable|string|size:3',
                'issue_date' => 'nullable|date',
                'due_date' => 'nullable|date',
                'po_ref' => 'nullable|string|max:255',
                'line_items' => 'nullable|array',
                'external_ref' => 'nullable|string|max:255',
                'metadata' => 'nullable|array',
            ]);
            $data['source'] = $source;

            $invoice = $orchestrator->ingestData($request->user(), $data);
        }

        return response()->json($invoice->load('vendor'), 201);
    }

    /** Phase 2 of a deferred upload: read the stored document and route it. */
    public function extract(Request $request, string $invoice, PayablesOrchestrator $orchestrator)
    {
        $model = $request->user()->invoices()->findOrFail($invoice);
        $orchestrator->processExtraction($model);

        return response()->json($model->refresh()->load('vendor'));
    }

    public function approve(Request $request, string $invoice, PayablesOrchestrator $orchestrator)
    {
        $model = $request->user()->invoices()->findOrFail($invoice);
        $payout = $orchestrator->approve($model, $request->user());

        return response()->json([
            'invoice' => $model->refresh()->load('vendor'),
            'payout' => $payout,
        ]);
    }

    public function hold(Request $request, string $invoice, PayablesOrchestrator $orchestrator)
    {
        $model = $request->user()->invoices()->findOrFail($invoice);
        $orchestrator->hold($model);

        return response()->json($model->refresh());
    }

    public function reject(Request $request, string $invoice, PayablesOrchestrator $orchestrator)
    {
        $model = $request->user()->invoices()->findOrFail($invoice);
        $orchestrator->reject($model);

        return response()->json($model->refresh());
    }
}
