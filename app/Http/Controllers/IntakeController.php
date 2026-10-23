<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Payables\PayablesOrchestrator;
use Illuminate\Http\Request;

/**
 * Public invoice intake. A vendor visits /u/{handle} and submits an invoice
 * straight into that account's queue — no login, no emailing it over. Anything
 * from a new/unknown vendor lands in Needs review (the rule engine never
 * auto-pays a stranger), so a public endpoint is safe by design.
 */
class IntakeController extends Controller
{
    public function show(string $handle)
    {
        $owner = User::where('handle', $handle)->firstOrFail();

        return view('intake', ['owner' => $owner]);
    }

    public function store(Request $request, string $handle, PayablesOrchestrator $orchestrator)
    {
        $owner = User::where('handle', $handle)->firstOrFail();

        $data = $request->validate([
            'vendor_name' => 'required|string|max:255',
            'vendor_email' => 'required|email|max:255',   // so the owner can actually pay them
            'invoice_number' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'due_date' => 'nullable|date',
            'file' => 'nullable|file|max:10240',
        ]);

        $invoice = $orchestrator->ingestData($owner, [
            'vendor_name' => $data['vendor_name'],
            'vendor_email' => $data['vendor_email'],
            'invoice_number' => $data['invoice_number'] ?? null,
            'amount' => $data['amount'],
            'currency' => strtoupper($data['currency']),
            'due_date' => $data['due_date'] ?? null,
            'source' => 'intake',
        ]);

        // Keep the uploaded document attached for reference.
        if ($request->hasFile('file')) {
            $invoice->update([
                'file_path' => $request->file('file')->store('invoices'),
                'original_filename' => $request->file('file')->getClientOriginalName(),
            ]);
        }

        return back()->with('sent', $owner->name);
    }
}
