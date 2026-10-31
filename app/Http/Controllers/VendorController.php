<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Services\Payouts\DwollaConnector;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    /** Vendors page — manage who gets paid and attach each one's bank for ACH. */
    public function index(Request $request)
    {
        $vendors = $request->user()->vendors()->orderBy('name')->get();

        return view('vendors', ['vendors' => $vendors]);
    }

    /**
     * Register a vendor's bank as a Dwolla funding source so they can be paid by
     * ACH. A vendor only *receives*, so a receive-only (unverified) funding
     * source is sufficient — no Plaid/verification needed on their side.
     */
    public function storeBank(Request $request, Vendor $vendor, DwollaConnector $dwolla)
    {
        abort_unless($vendor->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'routing_number' => 'required|string|size:9',
            'account_number' => 'required|string|max:17',
            'account_type' => 'required|in:checking,savings',
        ]);

        $result = $dwolla->createFundingSource([
            'name' => $vendor->name,
            'email' => $vendor->email,
            'routing_number' => $data['routing_number'],
            'account_number' => $data['account_number'],
            'account_type' => $data['account_type'],
        ]);

        if (! $result['ok']) {
            $message = $result['message'] ?? 'Could not add this bank — check the routing and account numbers.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'errors' => ['routing_number' => [$message]]], 422);
            }

            return back()->withErrors(['routing_number' => $message]);
        }

        $vendor->update(['dwolla_funding_source_id' => $result['funding_source_id']]);

        if ($request->expectsJson()) {
            return response()->json(['connected' => true, 'vendor_id' => $vendor->id]);
        }

        return back()->with('status', "Bank added for {$vendor->name} — they can now be paid by ACH.");
    }
}
