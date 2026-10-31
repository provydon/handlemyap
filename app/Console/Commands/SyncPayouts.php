<?php

namespace App\Console\Commands;

use App\Models\Payout;
use App\Models\User;
use App\Services\Payables\PayoutReconciler;
use Illuminate\Console\Command;

class SyncPayouts extends Command
{
    protected $signature = 'handlemyap:sync-payouts';

    protected $description = 'Reconcile in-flight payouts with their real settlement status';

    public function handle(PayoutReconciler $reconciler): int
    {
        // Reconcile per-user, since each pays from their own connected account.
        $userIds = Payout::query()
            ->where('status', Payout::STATUS_PROCESSING)
            ->whereNotNull('provider_ref')
            ->distinct()
            ->pluck('user_id');

        $total = 0;
        foreach (User::whereIn('id', $userIds)->get() as $user) {
            $total += $reconciler->reconcile($user);
        }

        $this->info("Reconciled {$total} payout(s).");

        return self::SUCCESS;
    }
}
