<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\Payables\PayablesOrchestrator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard');
    }

    /** JSON feed for the AG Grid queue + the headline stats. */
    public function data(Request $request, \App\Services\Payables\PayoutReconciler $reconciler)
    {
        // Best-effort: advance any in-flight PayPal payouts before rendering, so
        // the queue reflects real settlement without needing a background worker.
        rescue(fn () => $reconciler->reconcile($request->user()), report: false);

        $invoices = $request->user()->invoices()
            ->with('vendor')->latest()->limit(300)->get()
            ->map(fn (Invoice $i) => [
                'id' => $i->id,
                'vendor' => $i->vendor?->name ?? $i->vendor_name,
                'email' => $i->vendor?->email,
                'invoice_number' => $i->invoice_number,
                'amount' => $i->amount !== null ? (float) $i->amount : null,
                'currency' => $i->currency,
                'due_date' => $i->due_date?->toDateString(),
                'status' => $i->status,
                'confidence' => $i->confidence,
                'reasons' => $i->review_reasons ? implode(' · ', $i->review_reasons) : null,
                'source' => $i->source,
                'file_url' => $i->file_path ? url("/app/invoices/{$i->id}/file") : null,
            ]);

        return response()->json([
            'invoices' => $invoices,
            'stats' => [
                'needs_review' => $invoices->where('status', Invoice::STATUS_NEEDS_REVIEW)->count(),
                'scheduled' => $invoices->where('status', Invoice::STATUS_SCHEDULED)->count(),
                'paid' => $invoices->where('status', Invoice::STATUS_PAID)->count(),
                'paid_amount' => round($invoices->where('status', Invoice::STATUS_PAID)->sum('amount'), 2),
            ],
        ]);
    }

    /** Add an invoice from the dashboard — a file upload or a quick manual entry. */
    public function store(Request $request, PayablesOrchestrator $orchestrator)
    {
        if ($request->hasFile('file')) {
            $request->validate(['file' => 'required|file|max:10240']);
            $orchestrator->ingestUpload($request->user(), $request->file('file'), ['source' => 'upload']);
        } else {
            $data = $request->validate([
                'vendor_name' => 'required|string|max:255',
                'vendor_email' => 'nullable|email|max:255',
                'invoice_number' => 'nullable|string|max:255',
                'amount' => 'required|numeric|min:0',
                'currency' => 'required|string|size:3',
                'due_date' => 'nullable|date',
            ]);
            $data['source'] = 'manual';
            $orchestrator->ingestData($request->user(), $data);
        }

        return back()->with('status', 'Invoice added.');
    }

    /**
     * Serve the stored invoice document. For a private S3 bucket we redirect to
     * a short-lived signed URL so the browser fetches straight from S3; on the
     * local dev disk (which can't sign) we stream it through the app.
     */
    public function file(Request $request, Invoice $invoice)
    {
        $this->authorizeOwner($request, $invoice);
        abort_unless($invoice->file_path, 404);

        $disk = Storage::disk(config('handlemyap.uploads_disk'));
        abort_unless($disk->exists($invoice->file_path), 404);

        try {
            return redirect($disk->temporaryUrl($invoice->file_path, now()->addMinutes(5)));
        } catch (\RuntimeException) {
            return $disk->download($invoice->file_path, $invoice->original_filename);
        }
    }

    public function approve(Request $request, Invoice $invoice, PayablesOrchestrator $orchestrator)
    {
        $this->authorizeOwner($request, $invoice);
        $orchestrator->approve($invoice, $request->user());

        return back()->with('status', 'Approved & paid.');
    }

    public function hold(Request $request, Invoice $invoice, PayablesOrchestrator $orchestrator)
    {
        $this->authorizeOwner($request, $invoice);
        $orchestrator->hold($invoice);

        return back()->with('status', 'Held.');
    }

    public function reject(Request $request, Invoice $invoice, PayablesOrchestrator $orchestrator)
    {
        $this->authorizeOwner($request, $invoice);
        $orchestrator->reject($invoice);

        return back()->with('status', 'Rejected.');
    }

    private function authorizeOwner(Request $request, Invoice $invoice): void
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);
    }
}
