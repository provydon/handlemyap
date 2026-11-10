<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\Payables\PayablesOrchestrator;
use App\Services\Payouts\PayPalCheckout;
use Illuminate\Http\Request;

/**
 * The interactive "Pay with PayPal" flow (PayPal Checkout / Orders v2): the AP
 * user is sent to PayPal, logs in, pays the invoice from their own balance or
 * card, and the money lands with the vendor (their PayPal, when on file).
 *
 *   start()  → create the order, hand the browser PayPal's approve URL
 *   return() → PayPal sends the user back here; we capture and mark it paid
 *   cancel() → user backed out at PayPal
 *
 * With no PayPal credentials the app runs in demo mode: start() settles the
 * invoice immediately so the whole flow is demonstrable offline.
 */
class InvoicePaymentController extends Controller
{
    public function start(Request $request, Invoice $invoice, PayPalCheckout $paypal, PayablesOrchestrator $orchestrator)
    {
        $this->authorizeOwner($request, $invoice);

        if ($invoice->status === Invoice::STATUS_PAID) {
            return $this->fail($request, 'This invoice is already paid.');
        }
        if ($invoice->amount <= 0 || blank($invoice->currency)) {
            return $this->fail($request, 'This invoice needs an amount and currency before it can be paid.');
        }

        // Demo mode — no PayPal app configured, or the whole app is forced
        // offline (gateway=fake): settle it so the flow still runs end-to-end.
        $simulate = ! $paypal->configured() || config('handlemyap.gateway') === 'fake';
        if ($simulate) {
            $orchestrator->markPaidByCheckout($invoice, null, null, demo: true);

            if ($request->expectsJson()) {
                return response()->json(['paid' => true, 'demo' => true]);
            }

            return redirect()->route('dashboard')->with('status', 'Paid via PayPal (demo mode — no live PayPal connected).');
        }

        $result = $paypal->createOrder(
            $invoice,
            returnUrl: route('invoice.pay.return', $invoice),
            cancelUrl: route('invoice.pay.cancel', $invoice),
            payeeEmail: $this->payeeEmail($invoice),
        );

        if (! $result['ok'] || blank($result['approve_url'])) {
            return $this->fail($request, $result['message'] ?? 'Could not start the PayPal payment — please retry.');
        }

        if ($request->expectsJson()) {
            return response()->json(['redirect' => $result['approve_url']]);
        }

        return redirect()->away($result['approve_url']);
    }

    /**
     * JSON — create an order for the in-page smart-button popup. The PayPal JS
     * SDK calls this from its `createOrder` callback and expects just the id.
     */
    public function order(Request $request, Invoice $invoice, PayPalCheckout $paypal)
    {
        $this->authorizeOwner($request, $invoice);

        if ($invoice->status === Invoice::STATUS_PAID) {
            return response()->json(['message' => 'This invoice is already paid.'], 422);
        }
        if ($invoice->amount <= 0 || blank($invoice->currency)) {
            return response()->json(['message' => 'This invoice needs an amount and currency before it can be paid.'], 422);
        }
        if (! $paypal->configured()) {
            return response()->json(['message' => 'PayPal is not configured.'], 422);
        }

        $result = $paypal->createOrder(
            $invoice,
            returnUrl: route('invoice.pay.return', $invoice),
            cancelUrl: route('invoice.pay.cancel', $invoice),
            payeeEmail: $this->payeeEmail($invoice),
        );

        if (! $result['ok'] || blank($result['id'])) {
            return response()->json(['message' => $result['message'] ?? 'Could not start the PayPal payment.'], 422);
        }

        return response()->json(['id' => $result['id']]);
    }

    /**
     * JSON — capture an order the payer approved in the popup, then mark the
     * invoice paid. Called from the SDK's `onApprove` callback.
     */
    public function capture(Request $request, Invoice $invoice, PayPalCheckout $paypal, PayablesOrchestrator $orchestrator)
    {
        $this->authorizeOwner($request, $invoice);

        if ($invoice->status === Invoice::STATUS_PAID) {
            return response()->json(['ok' => true, 'already' => true]);
        }

        $data = $request->validate(['order_id' => 'required|string']);

        $capture = $paypal->captureOrder($data['order_id']);
        if (! $capture['ok']) {
            return response()->json(['ok' => false, 'message' => $capture['message'] ?? 'The PayPal payment did not complete.'], 422);
        }

        $orchestrator->markPaidByCheckout($invoice, $data['order_id'], $capture['capture_id']);

        return response()->json(['ok' => true, 'status' => 'paid']);
    }

    public function return(Request $request, Invoice $invoice, PayPalCheckout $paypal, PayablesOrchestrator $orchestrator)
    {
        $this->authorizeOwner($request, $invoice);

        if ($invoice->status === Invoice::STATUS_PAID) {
            return redirect()->route('dashboard')->with('status', 'This invoice is already paid.');
        }

        $orderId = $request->string('token')->toString(); // PayPal returns ?token={orderId}
        if (blank($orderId)) {
            return redirect()->route('dashboard')->with('status', 'PayPal did not return a payment to capture.');
        }

        $capture = $paypal->captureOrder($orderId);

        if (! $capture['ok']) {
            return redirect()->route('dashboard')->with('status', $capture['message'] ?? 'The PayPal payment did not complete.');
        }

        $orchestrator->markPaidByCheckout($invoice, $orderId, $capture['capture_id']);

        $vendor = $invoice->vendor?->name ?: $invoice->vendor_name ?: 'the vendor';

        return redirect()->route('dashboard')->with(
            'status',
            "Paid {$invoice->currency} ".number_format((float) $invoice->amount, 2)." to {$vendor} via PayPal."
        );
    }

    public function cancel(Request $request, Invoice $invoice)
    {
        $this->authorizeOwner($request, $invoice);

        return redirect()->route('dashboard')->with('status', 'PayPal payment canceled — the invoice is untouched.');
    }

    /** The vendor's PayPal address money should go to: their 'paypal' method, else their email. */
    private function payeeEmail(Invoice $invoice): ?string
    {
        $invoice->loadMissing('vendor.payoutMethods');

        return $invoice->vendor?->payoutMethod('paypal')?->recipient_ref
            ?: $invoice->vendor?->email;
    }

    private function fail(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return redirect()->route('dashboard')->with('status', $message);
    }

    private function authorizeOwner(Request $request, Invoice $invoice): void
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);
    }
}
