<?php

namespace App\Services\Payables;

use App\Models\AutoApprovalRule;
use App\Models\Invoice;
use App\Models\Payout;

/**
 * The autonomy dial, evaluated deterministically. Returns whether an invoice
 * may pay itself and, if not, the plain-language reasons it needs a human.
 * Those reasons double as the "why is this in my queue?" explanation in the UI.
 */
class RuleEngine
{
    public function __construct(private readonly DuplicateDetector $duplicates) {}

    /**
     * @return array{auto_approve: bool, reasons: list<string>}
     */
    public function evaluate(Invoice $invoice, ?AutoApprovalRule $rule): array
    {
        $reasons = [];

        // Facts we always need, regardless of rules.
        if (! ($invoice->amount > 0)) {
            $reasons[] = 'Missing or zero amount';
        }
        if (blank($invoice->currency)) {
            $reasons[] = 'Missing currency';
        }
        if (! $invoice->vendor?->isPayable()) {
            $reasons[] = 'Vendor has no payout email';
        }
        if ($invoice->confidence !== null && $invoice->confidence < 60) {
            $reasons[] = 'Low extraction confidence';
        }

        if (! $rule || ! $rule->enabled) {
            $reasons[] = 'Auto-approval is off';

            return ['auto_approve' => false, 'reasons' => array_values(array_unique($reasons))];
        }

        if ($rule->known_vendors_only && ! $invoice->vendor_id) {
            $reasons[] = 'New vendor (not yet approved)';
        }
        if ($rule->max_amount !== null && $invoice->amount > (float) $rule->max_amount) {
            $reasons[] = 'Amount over your auto-approve limit';
        }
        if (is_array($rule->allowed_currencies) && $rule->allowed_currencies !== []
            && ! in_array($invoice->currency, $rule->allowed_currencies, true)) {
            $reasons[] = 'Currency not on your allow-list';
        }
        if ($rule->block_duplicates && $this->duplicates->isDuplicate($invoice)) {
            $reasons[] = 'Possible duplicate';
        }
        if ($rule->daily_cap !== null && $this->wouldBreachDailyCap($invoice, (float) $rule->daily_cap)) {
            $reasons[] = 'Daily auto-pay cap reached';
        }

        $reasons = array_values(array_unique($reasons));

        return ['auto_approve' => $reasons === [], 'reasons' => $reasons];
    }

    private function wouldBreachDailyCap(Invoice $invoice, float $cap): bool
    {
        $spentToday = (float) Payout::query()
            ->where('user_id', $invoice->user_id)
            ->whereIn('status', [Payout::STATUS_PENDING, Payout::STATUS_PROCESSING, Payout::STATUS_PAID])
            ->whereDate('created_at', today())
            ->sum('amount');

        return ($spentToday + (float) $invoice->amount) > $cap;
    }
}
