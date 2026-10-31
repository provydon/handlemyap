<?php

namespace App\Services\Payables;

use App\Models\Invoice;
use App\Models\Payout;
use App\Models\User;
use App\Services\Payouts\PayoutGatewayResolver;
use Illuminate\Support\Facades\Cache;

/**
 * Some payouts settle asynchronously — accepted first, terminal (SUCCESS/FAILED)
 * later. This brings our records in line with the rail's actual state: it polls
 * each in-flight payout and advances the payout and its invoice to paid/failed.
 * Run on a schedule, and opportunistically when the dashboard loads so the UI
 * self-heals without a worker.
 */
class PayoutReconciler
{
    public function __construct(private readonly PayoutGatewayResolver $gateways) {}

    /** @return int number of payouts advanced to a terminal state */
    public function reconcile(User $user): int
    {
        // Status is read from the same (merchant-owned) account that sent it.
        $gateway = $this->gateways->for($user);

        $payouts = Payout::query()
            ->where('status', Payout::STATUS_PROCESSING)
            ->whereNotNull('provider_ref')
            ->where('user_id', $user->id)
            ->with('invoice')
            ->get();

        $advanced = 0;

        foreach ($payouts as $payout) {
            $raw = rescue(fn () => $gateway->status($payout->provider_ref), null, false);
            if ($raw === null) {
                continue;
            }

            $terminal = match ($raw) {
                'SUCCESS' => Payout::STATUS_PAID,
                'FAILED' => Payout::STATUS_FAILED,
                default => null, // still PENDING / processing
            };
            if ($terminal === null) {
                continue;
            }

            $payout->update([
                'status' => $terminal,
                'provider_status' => $raw,
                'paid_at' => $terminal === Payout::STATUS_PAID ? now() : null,
            ]);

            $payout->invoice?->update([
                'status' => $terminal === Payout::STATUS_PAID ? Invoice::STATUS_PAID : Invoice::STATUS_FAILED,
            ]);

            Cache::forget("payout_balance:{$payout->user_id}"); // settlement changed the balance

            $advanced++;
        }

        return $advanced;
    }
}
