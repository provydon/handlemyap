<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payout;
use App\Services\Payables\PayablesOrchestrator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Mint a short-lived token so the dashboard can call the public REST API
        // (/api/v1) just like any external integrator — the UI dogfoods its own
        // API. Keep exactly one such token per user so they don't pile up.
        $user->tokens()->where('name', 'dashboard')->delete();
        $apiToken = $user->createToken('dashboard')->plainTextToken;

        return view('dashboard', ['apiToken' => $apiToken]);
    }

    /**
     * Dashboard summary — the headline metrics as a single grouped aggregate
     * (COUNT + SUM per status) computed in the database, so it stays cheap no
     * matter how many invoices exist (never loads rows into PHP to count them).
     * Also returns the distinct currencies in use, to fill the currency filter.
     */
    public function summary(Request $request)
    {
        $rows = $request->user()->invoices()
            ->selectRaw('status, COUNT(*) as cnt, COALESCE(SUM(amount), 0) as total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $count = fn (string $s) => (int) ($rows[$s]->cnt ?? 0);
        $sum = fn (string $s) => round((float) ($rows[$s]->total ?? 0), 2);

        return response()->json([
            'stats' => [
                'needs_review' => $count(Invoice::STATUS_NEEDS_REVIEW),
                'needs_review_amount' => $sum(Invoice::STATUS_NEEDS_REVIEW),
                'paid' => $count(Invoice::STATUS_PAID),
                'paid_amount' => $sum(Invoice::STATUS_PAID),
            ],
            'currencies' => $request->user()->invoices()
                ->whereNotNull('currency')->distinct()->orderBy('currency')->pluck('currency'),
        ]);
    }

    /**
     * The invoice queue — filtered and paginated in the database, so the client
     * only ever holds one page instead of the whole table. Vendor is
     * eager-loaded (no N+1).
     */
    public function queue(Request $request)
    {
        $query = $request->user()->invoices()->with(['vendor', 'payouts'])->latest();

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }
        if ($source = $request->string('source')->toString()) {
            $query->where('source', $source);
        }
        if ($currency = $request->string('currency')->toString()) {
            $query->where('currency', $currency);
        }
        if ($term = trim($request->string('search')->toString())) {
            $like = '%'.$term.'%';
            $query->where(function ($w) use ($like) {
                $w->where('vendor_name', 'like', $like)       // client / vendor name
                    ->orWhere('invoice_number', 'like', $like)
                    ->orWhere('po_ref', 'like', $like)
                    ->orWhereRaw('CAST(amount AS TEXT) LIKE ?', [$like]) // match by amount
                    ->orWhereHas('vendor', fn ($v) => $v
                        ->where('name', 'like', $like)->orWhere('email', 'like', $like));
            });
        }

        $page = $query->paginate(50);

        return response()->json([
            'invoices' => collect($page->items())->map(fn (Invoice $i) => $this->present($i)),
            'has_more' => $page->hasMorePages(),
            'next_page' => $page->currentPage() + 1,
        ]);
    }

    /** Shape an invoice row for the dashboard queue. @return array<string, mixed> */
    private function present(Invoice $i): array
    {
        $payout = $i->status === Invoice::STATUS_PAID ? $this->paidPayout($i) : null;

        return [
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
            // Paid invoices link out to the PayPal transaction (the capture);
            // demo-mode captures aren't real transactions, so they carry a flag
            // instead of a link.
            'payment_url' => $this->paymentUrl($payout),
            'payment_ref' => $payout?->provider_item_ref ?: $payout?->provider_ref,
            'payment_demo' => $payout?->provider_status === 'DEMO_CAPTURE',
        ];
    }

    /** The settled payout for a paid invoice (eager-loaded; no extra query). */
    private function paidPayout(Invoice $i): ?Payout
    {
        $payouts = $i->relationLoaded('payouts') ? $i->payouts : $i->payouts()->get();

        return $payouts->firstWhere('status', Payout::STATUS_PAID) ?? $payouts->last();
    }

    /**
     * A link to the PayPal transaction for a settled payout. The capture id is
     * PayPal's transaction id; the viewer (the AP operator who paid) sees it in
     * their PayPal activity. Returns null when there's nothing real to link to
     * (demo capture, non-PayPal rail, or no reference recorded).
     */
    private function paymentUrl(?Payout $payout): ?string
    {
        if (! $payout || $payout->rail !== 'paypal' || $payout->provider_status === 'DEMO_CAPTURE') {
            return null;
        }

        $txn = $payout->provider_item_ref ?: $payout->provider_ref; // capture id, else order id
        if (blank($txn)) {
            return null;
        }

        $live = in_array(config('handlemyap.paypal.env'), ['production', 'prod', 'live'], true);
        $host = $live ? 'https://www.paypal.com' : 'https://www.sandbox.paypal.com';

        return "{$host}/activity/payment/{$txn}";
    }

    /** The single-invoice page — full detail, document, payment link, actions. */
    public function show(Request $request, Invoice $invoice)
    {
        $this->authorizeOwner($request, $invoice);

        $invoice->load(['vendor', 'payouts']);
        $payout = $invoice->status === Invoice::STATUS_PAID ? $this->paidPayout($invoice) : null;

        return view('invoice', [
            'invoice' => $invoice,
            'payout' => $payout,
            'paymentUrl' => $this->paymentUrl($payout),
            'fileUrl' => $invoice->file_path ? route('invoice.file', $invoice) : null,
        ]);
    }

    /**
     * Remove the recorded payment from an invoice: delete its payout(s) and send
     * it back to "needs review" so it can be acted on again. This only clears the
     * record in HandlemyAP — it does NOT refund the money through PayPal.
     */
    public function removePayment(Request $request, Invoice $invoice, PayablesOrchestrator $orchestrator)
    {
        $this->authorizeOwner($request, $invoice);
        $orchestrator->removePayment($invoice);

        return redirect()
            ->route('invoice.show', $invoice)
            ->with('status', 'Payment removed — the invoice is back in Needs review. (No PayPal refund was issued.)');
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

        abort_unless(Storage::exists($invoice->file_path), 404);

        try {
            // Private S3 bucket → short-lived signed URL the browser fetches directly.
            return redirect(Storage::temporaryUrl($invoice->file_path, now()->addMinutes(5)));
        } catch (\RuntimeException) {
            // Local dev disk can't sign — stream it through the app instead.
            return Storage::download($invoice->file_path, $invoice->original_filename);
        }
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
