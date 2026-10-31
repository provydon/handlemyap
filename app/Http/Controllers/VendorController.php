<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Services\Payouts\DwollaConnector;
use App\Services\Payouts\PayoutGatewayResolver;
use App\Services\Payouts\PaystackConnector;
use App\Services\Payouts\RailCatalog;
use App\Services\Payouts\StripeGlobalPayoutsConnector;
use App\Services\Payouts\WiseConnector;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function __construct(
        private readonly PayoutGatewayResolver $resolver,
        private readonly RailCatalog $catalog,
    ) {}

    /** Vendors page — manage who gets paid and attach each one's bank per rail. */
    public function index(Request $request)
    {
        $vendors = $request->user()->vendors()->with('payoutMethods')->orderBy('name')->get();

        // Resolve each vendor's rail (by country) so the view shows the right
        // bank form and whether a destination is already on file.
        $routing = $vendors->mapWithKeys(function (Vendor $vendor) use ($request) {
            $rail = $this->resolver->railFor($request->user(), $vendor);

            return [$vendor->id => [
                'rail' => $rail,
                'country' => $this->resolver->countryFor($vendor),
                'provider' => $rail ? ($this->catalog->get($rail)['provider'] ?? null) : null,
                'usable' => $rail ? $this->catalog->isUsable($rail) : false,
                'connected' => $rail ? $vendor->hasPayoutMethod($rail) : false,
            ]];
        });

        return view('vendors', [
            'vendors' => $vendors,
            'routing' => $routing,
            'banks' => app(PaystackConnector::class)->banks('NGN'),
        ]);
    }

    /**
     * Attach a vendor's bank for the rail that serves their country (and, where a
     * country has several, the one the business chose): a Dwolla funding source
     * for the US, a Paystack recipient for Nigeria, a Wise or Stripe recipient
     * for the UK. Either way we keep only the provider's destination id.
     */
    public function storeBank(Request $request, Vendor $vendor)
    {
        abort_unless($vendor->user_id === $request->user()->id, 403);

        $rail = $this->resolver->railFor($request->user(), $vendor);

        return match ($rail) {
            'dwolla' => $this->storeDwolla($request, $vendor),
            'paystack' => $this->storePaystack($request, $vendor),
            'wise' => $this->storeWise($request, $vendor),
            'stripe' => $this->storeStripe($request, $vendor),
            default => $this->bail($request, 'No payout rail is available for this vendor’s country yet.'),
        };
    }

    private function storeDwolla(Request $request, Vendor $vendor)
    {
        $data = $request->validate([
            'routing_number' => 'required|string|size:9',
            'account_number' => 'required|string|max:17',
            'account_type' => 'required|in:checking,savings',
        ]);

        $result = app(DwollaConnector::class)->createFundingSource([
            'name' => $vendor->name,
            'email' => $vendor->email,
            'routing_number' => $data['routing_number'],
            'account_number' => $data['account_number'],
            'account_type' => $data['account_type'],
        ]);

        if (! $result['ok']) {
            return $this->bail($request, $result['message'] ?? 'Could not add this bank — check the routing and account numbers.', 'routing_number');
        }

        $vendor->payoutMethods()->updateOrCreate(
            ['rail' => 'dwolla'],
            [
                'recipient_ref' => $result['funding_source_id'],
                'details' => ['account_type' => $data['account_type'], 'account_last4' => substr($data['account_number'], -4)],
            ],
        );

        if ($request->expectsJson()) {
            return response()->json(['connected' => true, 'vendor_id' => $vendor->id]);
        }

        return back()->with('status', "Bank added for {$vendor->name} — they can now be paid by ACH.");
    }

    private function storePaystack(Request $request, Vendor $vendor)
    {
        $data = $request->validate([
            'bank_code' => 'required|string|max:10',
            'account_number' => 'required|string|max:20',
        ]);

        $result = app(PaystackConnector::class)->createTransferRecipient(
            $vendor->name,
            $data['account_number'],
            $data['bank_code'],
            $vendor->currency ?: 'NGN',
        );

        if (! $result['ok']) {
            return $this->bail($request, $result['message'] ?? 'Could not add this bank — check the account number and bank.', 'account_number');
        }

        $vendor->payoutMethods()->updateOrCreate(
            ['rail' => 'paystack'],
            [
                'recipient_ref' => $result['recipient_code'],
                'details' => [
                    'bank_code' => $data['bank_code'],
                    'bank_name' => $result['bank_name'],
                    'account_name' => $result['account_name'],
                    'account_last4' => substr($data['account_number'], -4),
                ],
                'verified_at' => now(),
            ],
        );

        if ($request->expectsJson()) {
            return response()->json(['connected' => true, 'vendor_id' => $vendor->id]);
        }

        return back()->with('status', "Bank added for {$vendor->name} — they can now be paid by Paystack transfer.");
    }

    /** UK bank details (sort code + account number) — shared by Wise and Stripe. */
    private function ukBankDetails(Request $request): array
    {
        $data = $request->validate([
            'sort_code' => 'required|string|max:10',
            'account_number' => 'required|string|max:10',
        ]);

        return [
            'sort_code' => preg_replace('/\D/', '', $data['sort_code']),
            'account_number' => preg_replace('/\D/', '', $data['account_number']),
        ];
    }

    private function storeWise(Request $request, Vendor $vendor)
    {
        $bank = $this->ukBankDetails($request);

        $result = app(WiseConnector::class)->createRecipient(
            $vendor->name, $bank['sort_code'], $bank['account_number'], $vendor->currency ?: 'GBP',
        );

        if (! $result['ok']) {
            return $this->bail($request, $result['message'] ?? 'Could not add this bank with Wise.', 'account_number');
        }

        $vendor->payoutMethods()->updateOrCreate(
            ['rail' => 'wise'],
            [
                'recipient_ref' => $result['recipient_code'],
                'details' => ['sort_code' => $bank['sort_code'], 'account_last4' => substr($bank['account_number'], -4)],
                'verified_at' => now(),
            ],
        );

        if ($request->expectsJson()) {
            return response()->json(['connected' => true, 'vendor_id' => $vendor->id]);
        }

        return back()->with('status', "Bank added for {$vendor->name} — they can now be paid via Wise.");
    }

    private function storeStripe(Request $request, Vendor $vendor)
    {
        $bank = $this->ukBankDetails($request);

        $result = app(StripeGlobalPayoutsConnector::class)->createRecipient(
            $vendor->name, $bank['sort_code'], $bank['account_number'], $vendor->currency ?: 'GBP',
        );

        if (! $result['ok']) {
            return $this->bail($request, $result['message'] ?? 'Could not add this bank with Stripe.', 'account_number');
        }

        $vendor->payoutMethods()->updateOrCreate(
            ['rail' => 'stripe'],
            [
                'recipient_ref' => $result['recipient_code'],
                'details' => [
                    'payout_method' => $result['payout_method'],
                    'sort_code' => $bank['sort_code'],
                    'account_last4' => substr($bank['account_number'], -4),
                ],
                'verified_at' => now(),
            ],
        );

        if ($request->expectsJson()) {
            return response()->json(['connected' => true, 'vendor_id' => $vendor->id]);
        }

        return back()->with('status', "Bank added for {$vendor->name} — they can now be paid via Stripe Global Payouts.");
    }

    private function bail(Request $request, string $message, string $field = 'bank')
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'errors' => [$field => [$message]]], 422);
        }

        return back()->withErrors([$field => $message]);
    }
}
