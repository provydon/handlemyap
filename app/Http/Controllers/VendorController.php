<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    /** Vendors page — manage who gets paid and store each one's PayPal email. */
    public function index(Request $request)
    {
        $vendors = $request->user()->vendors()->with('payoutMethods')->orderBy('name')->get();

        $routing = $vendors->mapWithKeys(fn (Vendor $vendor) => [$vendor->id => [
            'connected' => $vendor->hasPayoutMethod('paypal'),
            'paypal_email' => $vendor->payoutMethod('paypal')?->recipient_ref,
        ]]);

        return view('vendors', ['vendors' => $vendors, 'routing' => $routing]);
    }

    /** Store (or update) a vendor's PayPal email — the destination a payment lands in. */
    public function storeBank(Request $request, Vendor $vendor)
    {
        abort_unless($vendor->user_id === $request->user()->id, 403);

        $data = $request->validate(['paypal_email' => 'required|email|max:255']);
        $email = strtolower($data['paypal_email']);

        $vendor->payoutMethods()->updateOrCreate(
            ['rail' => 'paypal'],
            [
                'recipient_ref' => $email,
                'details' => ['paypal_email' => $email],
                'verified_at' => now(),
            ],
        );

        if ($request->expectsJson()) {
            return response()->json(['connected' => true, 'vendor_id' => $vendor->id]);
        }

        return back()->with('status', "PayPal added for {$vendor->name} — they can now be paid to their PayPal.");
    }
}
