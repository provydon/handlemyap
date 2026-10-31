<?php

namespace App\Http\Controllers;

use App\Services\Payouts\DwollaConnector;
use App\Services\Payouts\PlaidConnector;
use App\Services\Payouts\RailCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    /** Connect the business's bank for ACH payouts (via Dwolla, platform-level). */
    public function connectBank(Request $request, DwollaConnector $connector)
    {
        $data = $request->validate([
            'bank_name' => 'required|string|max:255',
            'routing_number' => 'required|string|size:9',
            'account_number' => 'required|string|max:17',
            'account_type' => 'required|in:checking,savings',
        ]);

        // Register the bank as a Dwolla funding source and keep only its id.
        $result = $connector->createFundingSource([
            'name' => $data['bank_name'],
            'email' => $request->user()->email,
            'routing_number' => $data['routing_number'],
            'account_number' => $data['account_number'],
            'account_type' => $data['account_type'],
        ]);

        if (! $result['ok']) {
            $message = $result['message'] ?? 'Could not connect this bank with Dwolla — check the routing and account numbers.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'errors' => ['routing_number' => [$message]]], 422);
            }

            return back()->withErrors(['routing_number' => $message]);
        }

        $user = $request->user();
        $user->update([
            'dwolla_customer_id' => $result['customer_id'],
            'dwolla_funding_source_id' => $result['funding_source_id'],
            'dwolla_bank_name' => $data['bank_name'],
            'dwolla_connected_at' => now(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'connected' => true,
                'bank_name' => $user->dwolla_bank_name,
            ]);
        }

        return back()->with('status', 'Bank connected for ACH payouts.');
    }

    public function disconnectBank(Request $request)
    {
        $request->user()->update([
            'dwolla_customer_id' => null,
            'dwolla_funding_source_id' => null,
            'dwolla_bank_name' => null,
            'dwolla_connected_at' => null,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['connected' => false]);
        }

        return back()->with('status', 'Bank disconnected.');
    }

    /** Mint a Plaid Link token for the frontend to open instant verification. */
    public function plaidLinkToken(Request $request, PlaidConnector $plaid)
    {
        if (! $plaid->configured()) {
            return response()->json(['message' => 'Instant verification is not configured.'], 422);
        }

        $token = $plaid->createLinkToken($request->user());

        if (! $token) {
            return response()->json(['message' => 'Could not start Plaid — try again.'], 422);
        }

        return response()->json(['link_token' => $token]);
    }

    /**
     * PRIMARY bank connection: Plaid instant verification → Dwolla verified
     * funding source (one that can actually SEND). The frontend sends the
     * public_token + chosen account from Plaid Link.
     */
    public function connectBankPlaid(Request $request, PlaidConnector $plaid, DwollaConnector $dwolla)
    {
        $data = $request->validate([
            'public_token' => 'required|string',
            'account_id' => 'required|string',
            'bank_name' => 'required|string|max:255',
        ]);

        $fail = fn (string $msg) => $request->expectsJson()
            ? response()->json(['message' => $msg, 'errors' => ['public_token' => [$msg]]], 422)
            : back()->withErrors(['public_token' => $msg]);

        $accessToken = $plaid->exchangePublicToken($data['public_token']);
        if (! $accessToken) {
            return $fail('Could not verify the bank with Plaid — please retry.');
        }

        $processorToken = $plaid->createDwollaProcessorToken($accessToken, $data['account_id']);
        if (! $processorToken) {
            return $fail('Could not hand the verified bank to Dwolla — please retry.');
        }

        $user = $request->user();
        $result = $dwolla->createVerifiedFundingSourceFromPlaid([
            'name' => $data['bank_name'],
            'email' => $user->email,
            'business_name' => $user->name,
        ], $processorToken);

        if (! $result['ok']) {
            return $fail($result['message'] ?? 'Could not create the Dwolla funding source.');
        }

        $user->update([
            'dwolla_customer_id' => $result['customer_id'],
            'dwolla_funding_source_id' => $result['funding_source_id'],
            'dwolla_bank_name' => $data['bank_name'],
            'dwolla_connected_at' => now(),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['connected' => true, 'bank_name' => $user->dwolla_bank_name]);
        }

        return back()->with('status', 'Bank connected and verified for ACH payouts.');
    }

    public function updateHandle(Request $request)
    {
        $data = $request->validate([
            'handle' => [
                'required', 'string', 'max:40', 'regex:/^[a-z0-9][a-z0-9-]*$/',
                Rule::unique('users', 'handle')->ignore($request->user()->id),
            ],
        ]);

        $request->user()->update(['handle' => $data['handle']]);

        return back()->with('status', 'Your intake link was updated.');
    }

    public function updateCurrency(Request $request)
    {
        $data = $request->validate([
            'default_currency' => 'required|string|size:3',
        ]);

        $request->user()->update(['default_currency' => strtoupper($data['default_currency'])]);

        return back()->with('status', 'Default currency updated.');
    }

    public function index(Request $request, RailCatalog $catalog)
    {
        return view('settings', [
            'rule' => $request->user()->autoApprovalRule,
            'tokens' => $request->user()->tokens()->latest()->get(),
            'plainToken' => session('plainToken'),
            'catalog' => $catalog,
            'routes' => $request->user()->payout_routes ?? [],
        ]);
    }

    /**
     * Save the business's per-country rail choice — which payment method to use
     * for each country a vendor might be in. Only countries with more than one
     * available rail actually need a choice; the rest fall back to their single
     * rail.
     */
    public function updatePayoutRoutes(Request $request, RailCatalog $catalog)
    {
        $routes = [];
        foreach ($catalog->countries() as $country) {
            $picked = $request->input("route.{$country}");
            $available = array_keys($catalog->forCountry($country));

            if ($picked && in_array($picked, $available, true) && $catalog->isUsable($picked)) {
                $routes[$country] = $picked;
            }
        }

        $request->user()->update(['payout_routes' => $routes]);

        return back()->with('status', 'Payout routing saved.');
    }

    public function updateRule(Request $request)
    {
        $data = $request->validate([
            'enabled' => 'nullable|boolean',
            'max_amount' => 'nullable|numeric|min:0',
            'known_vendors_only' => 'nullable|boolean',
            'block_duplicates' => 'nullable|boolean',
            'daily_cap' => 'nullable|numeric|min:0',
        ]);

        $request->user()->autoApprovalRule()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'enabled' => $request->boolean('enabled'),
                'max_amount' => $data['max_amount'] ?? null,
                'known_vendors_only' => $request->boolean('known_vendors_only'),
                'block_duplicates' => $request->boolean('block_duplicates'),
                'daily_cap' => $data['daily_cap'] ?? null,
            ],
        );

        return back()->with('status', 'Auto-approval rules saved.');
    }

    public function createToken(Request $request)
    {
        $request->validate(['name' => 'required|string|max:64']);
        $token = $request->user()->createToken($request->string('name'));

        // Show the plaintext token exactly once.
        return back()->with('plainToken', $token->plainTextToken);
    }

    public function deleteToken(Request $request, string $id)
    {
        $request->user()->tokens()->where('id', $id)->delete();

        return back()->with('status', 'Token revoked.');
    }
}
