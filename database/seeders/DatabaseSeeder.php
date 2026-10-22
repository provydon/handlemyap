<?php

namespace Database\Seeders;

use App\Models\AutoApprovalRule;
use App\Models\Invoice;
use App\Models\Payout;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'demo@handlemyap.test'],
            ['name' => 'Demo', 'password' => Hash::make('password')],
        );

        AutoApprovalRule::updateOrCreate(
            ['user_id' => $user->id],
            ['enabled' => true, 'max_amount' => 500, 'known_vendors_only' => false, 'block_duplicates' => true],
        );

        if ($user->invoices()->exists()) {
            return; // idempotent
        }

        $vendors = collect([
            ['name' => 'Jane Q Notary', 'email' => 'jane@example.com'],
            ['name' => 'Rivera Signing Services', 'email' => 'ap@riverasigning.test'],
            ['name' => 'Northwind Supplies', 'email' => 'billing@northwind.test'],
        ])->map(fn ($v) => $user->vendors()->create($v + ['currency' => 'USD', 'country' => 'US']));

        // A couple already paid (with payout rows).
        foreach ([[220.00, 'INV-5012'], [410.00, 'INV-5013']] as [$amt, $num]) {
            $inv = $user->invoices()->create([
                'vendor_id' => $vendors[0]->id, 'vendor_name' => $vendors[0]->name,
                'invoice_number' => $num, 'amount' => $amt, 'currency' => 'USD',
                'due_date' => now()->subDay(), 'confidence' => 98, 'status' => Invoice::STATUS_PAID,
                'approved_at' => now()->subHours(3), 'approved_by' => 'auto', 'source' => 'api',
            ]);
            $user->payouts()->create([
                'invoice_id' => $inv->id, 'vendor_id' => $vendors[0]->id, 'amount' => $amt,
                'currency' => 'USD', 'status' => Payout::STATUS_PAID, 'paid_at' => now()->subHours(3),
                'paypal_batch_id' => 'DEMO-BATCH-'.$inv->id, 'paypal_status' => 'SUCCESS',
            ]);
        }

        // Needs review: over the auto-approve limit.
        $user->invoices()->create([
            'vendor_id' => $vendors[1]->id, 'vendor_name' => $vendors[1]->name,
            'invoice_number' => 'RS-8841', 'amount' => 1250.00, 'currency' => 'USD',
            'due_date' => now()->addDays(2), 'confidence' => 95, 'status' => Invoice::STATUS_NEEDS_REVIEW,
            'review_reasons' => ['Amount over your auto-approve limit'], 'source' => 'upload',
            'original_filename' => 'rivera-aug.pdf',
        ]);

        // Needs review: low confidence from a messy scan.
        $user->invoices()->create([
            'vendor_id' => $vendors[2]->id, 'vendor_name' => $vendors[2]->name,
            'invoice_number' => 'NW-331', 'amount' => 86.40, 'currency' => 'USD',
            'due_date' => now()->addDay(), 'confidence' => 45, 'status' => Invoice::STATUS_NEEDS_REVIEW,
            'review_reasons' => ['Low extraction confidence'], 'source' => 'upload',
            'original_filename' => 'northwind-scan.jpg',
        ]);

        // Scheduled for a future due date.
        $user->invoices()->create([
            'vendor_id' => $vendors[0]->id, 'vendor_name' => $vendors[0]->name,
            'invoice_number' => 'INV-5020', 'amount' => 175.00, 'currency' => 'USD',
            'due_date' => now()->addDays(5), 'confidence' => 97, 'status' => Invoice::STATUS_SCHEDULED,
            'approved_at' => now(), 'approved_by' => 'auto', 'source' => 'api',
        ]);
    }
}
