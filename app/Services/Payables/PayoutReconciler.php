<?php

namespace App\Services\Payables;

use App\Models\Invoice;
use App\Models\Payout;
use App\Models\User;
use App\Services\PayPal\PayoutGateway;

/**
 * PayPal Payouts settle asynchronously — a batch comes back PENDING/PROCESSING
 * and only later turns SUCCESS (or DENIED). This brings our records in line with
 * PayPal's actual state: it polls each in-flight batch and advances the payout
 * and its invoice to paid/failed. Run on a schedule, and opportunistically when
 * the dashboard loads so the UI self-heals without a worker.
 */
class PayoutReconciler
{
    public function __construct(private readonly PayoutGateway $gateway) {}

    /** @return int number of payouts advanced to a terminal state */
    public function reconcile(?User $user = null): int
    {
        $query = Payout::query()
            ->where('status', Payout::STATUS_PROCESSING)
            ->whereNotNull('paypal_batch_id')
            ->with('invoice');

        if ($user) {
            $query->where('user_id', $user->id);
        }

        $advanced = 0;

        foreach ($query->get() as $payout) {
            $raw = rescue(fn () => $this->gateway->status($payout->paypal_batch_id), null, false);
            if ($raw === null) {
                continue;
            }

            $terminal = match ($raw) {
                'SUCCESS' => Payout::STATUS_PAID,
                'DENIED', 'CANCELED' => Payout::STATUS_FAILED,
                default => null, // still PENDING/PROCESSING
            };
            if ($terminal === null) {
                continue;
            }

            $payout->update([
                'status' => $terminal,
                'paypal_status' => $raw,
                'paid_at' => $terminal === Payout::STATUS_PAID ? now() : null,
            ]);

            $payout->invoice?->update([
                'status' => $terminal === Payout::STATUS_PAID ? Invoice::STATUS_PAID : Invoice::STATUS_FAILED,
            ]);

            $advanced++;
        }

        return $advanced;
    }
}
