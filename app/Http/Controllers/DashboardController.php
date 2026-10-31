<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\Payables\PayablesOrchestrator;
use App\Services\Payables\PayoutReconciler;
use App\Services\Payouts\PayoutGatewayResolver;
use App\Services\Payouts\RailCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
                'scheduled' => $count(Invoice::STATUS_SCHEDULED),
                'scheduled_amount' => $sum(Invoice::STATUS_SCHEDULED),
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
        $query = $request->user()->invoices()->with('vendor')->latest();

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
                $w->where('vendor_name', 'like', $like)
                    ->orWhere('invoice_number', 'like', $like)
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
        ];
    }

    /**
     * The slow, rail-backed bits — loaded asynchronously after the grid is
     * already on screen. Reconciles in-flight payouts (throttled to once per
     * 20s per user) and returns one balance PER usable rail (a business can
     * fund several — a USD Dwolla balance, an NGN Paystack balance, …).
     * `reconciled` tells the client whether invoice statuses changed and the
     * queue needs a refresh.
     */
    public function balance(Request $request, PayoutReconciler $reconciler, PayoutGatewayResolver $gateways, RailCatalog $catalog)
    {
        $user = $request->user();
        $userId = $user->id;

        $reconciled = 0;
        // Cache::add is atomic: reconcile runs only when the 20s lock is free.
        if (Cache::add("reconcile:lock:{$userId}", true, now()->addSeconds(20))) {
            $reconciled = rescue(fn () => $reconciler->reconcile($user), 0, report: false);
        }

        $balances = Cache::remember(
            "payout_balances:{$userId}",
            now()->addSeconds(60),
            function () use ($user, $gateways, $catalog) {
                $out = [];
                foreach ($catalog->all() as $rail => $meta) {
                    if (! $catalog->isUsable($rail)) {
                        continue; // planned, or no platform creds
                    }

                    $bal = rescue(fn () => $gateways->gateway($rail, $user)->balance(), null, report: false);
                    if ($bal === null || ($bal['state'] ?? null) !== 'ok') {
                        continue; // rail not connected / unreadable for this business
                    }

                    $demo = $bal['demo'] ?? false;
                    $out[] = [
                        'rail' => $rail,
                        'provider' => $meta['provider'],
                        'method' => $meta['method'],
                        // In demo/fake mode every rail reports the same stub figure;
                        // label each in its own currency so the tiles read sensibly.
                        'currency' => $demo ? $meta['currency'] : ($bal['currency'] ?? $meta['currency']),
                        'available' => $bal['available'] ?? null,
                        'demo' => $demo,
                    ];
                }

                return $out;
            },
        );

        return response()->json([
            'balances' => $balances,
            'reconciled' => $reconciled,
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

        abort_unless(Storage::exists($invoice->file_path), 404);

        try {
            // Private S3 bucket → short-lived signed URL the browser fetches directly.
            return redirect(Storage::temporaryUrl($invoice->file_path, now()->addMinutes(5)));
        } catch (\RuntimeException) {
            // Local dev disk can't sign — stream it through the app instead.
            return Storage::download($invoice->file_path, $invoice->original_filename);
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
