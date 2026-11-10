<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

/**
 * Populates the demo workspace with a few realistic, payable invoices so the
 * hosted demo shows a full dashboard (and the AG Grid) on first load, rather
 * than an empty queue. Idempotent — safe to run on every boot.
 *
 * Not wired into DatabaseSeeder on purpose: local installs start empty
 * (see DatabaseSeeder); this runs only on the deployed demo via the start script.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'demo@handlemyap.test'],
            ['name' => 'Demo', 'password' => bcrypt('password'), 'handle' => 'demo'],
        );

        $vendors = [
            ['name' => 'ACME Studio LLC', 'email' => 'billing@acmestudio.example'],
            ['name' => 'Northwind Traders', 'email' => 'ap@northwind.example'],
        ];

        $vendorModels = [];
        foreach ($vendors as $v) {
            $vendor = Vendor::updateOrCreate(
                ['user_id' => $user->id, 'name' => $v['name']],
                ['email' => $v['email'], 'country' => 'US', 'currency' => 'USD'],
            );
            // Put a PayPal address on file so "Pay with PayPal" is ready to go.
            $vendor->payoutMethods()->updateOrCreate(
                ['rail' => 'paypal'],
                ['recipient_ref' => $v['email'], 'verified_at' => now()],
            );
            $vendorModels[$v['name']] = $vendor;
        }

        $invoices = [
            ['ACME Studio LLC', 'ACM-2026-0412', 4560.00, 14, [
                ['description' => 'Brand design sprint', 'qty' => 1, 'unit_price' => 2400, 'amount' => 2400],
                ['description' => 'Landing page (3 variants)', 'qty' => 3, 'unit_price' => 600, 'amount' => 1800],
                ['description' => 'Icon set', 'qty' => 40, 'unit_price' => 9, 'amount' => 360],
            ]],
            ['Northwind Traders', 'NW-88431', 1280.50, 7, [
                ['description' => 'Monthly logistics retainer', 'qty' => 1, 'unit_price' => 1280.50, 'amount' => 1280.50],
            ]],
            ['ACME Studio LLC', 'ACM-2026-0451', 900.00, 21, [
                ['description' => 'Motion graphics (30s)', 'qty' => 1, 'unit_price' => 900, 'amount' => 900],
            ]],
            ['Northwind Traders', 'NW-88502', 342.75, 3, [
                ['description' => 'Expedited freight surcharge', 'qty' => 1, 'unit_price' => 342.75, 'amount' => 342.75],
            ]],
        ];

        foreach ($invoices as [$vendorName, $number, $amount, $dueInDays, $lines]) {
            $vendor = $vendorModels[$vendorName];
            Invoice::updateOrCreate(
                ['user_id' => $user->id, 'invoice_number' => $number],
                [
                    'vendor_id' => $vendor->id,
                    'source' => 'upload',
                    'status' => Invoice::STATUS_NEEDS_REVIEW,
                    'vendor_name' => $vendorName,
                    'amount' => $amount,
                    'currency' => 'USD',
                    'issue_date' => now()->subDays(2),
                    'due_date' => now()->addDays($dueInDays),
                    'line_items' => $lines,
                    'confidence' => 96,
                ],
            );
        }
    }
}
