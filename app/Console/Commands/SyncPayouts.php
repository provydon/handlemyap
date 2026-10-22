<?php

namespace App\Console\Commands;

use App\Services\Payables\PayoutReconciler;
use Illuminate\Console\Command;

class SyncPayouts extends Command
{
    protected $signature = 'handlemyap:sync-payouts';

    protected $description = 'Reconcile in-flight PayPal payouts with their real settlement status';

    public function handle(PayoutReconciler $reconciler): int
    {
        $n = $reconciler->reconcile();
        $this->info("Reconciled {$n} payout(s).");

        return self::SUCCESS;
    }
}
