<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_aggregates_counts_in_the_database(): void
    {
        $user = User::factory()->create();
        Invoice::factory()->for($user)->create(['status' => Invoice::STATUS_NEEDS_REVIEW, 'amount' => 100, 'currency' => 'USD']);
        Invoice::factory()->for($user)->create(['status' => Invoice::STATUS_NEEDS_REVIEW, 'amount' => 50, 'currency' => 'USD']);
        Invoice::factory()->for($user)->create(['status' => Invoice::STATUS_PAID, 'amount' => 200, 'currency' => 'NGN']);

        // Another user's invoice must not leak in.
        Invoice::factory()->for(User::factory()->create())->create(['status' => Invoice::STATUS_PAID]);

        $this->actingAs($user)->getJson('/app/summary')
            ->assertOk()
            ->assertJsonPath('stats.needs_review', 2)
            ->assertJsonPath('stats.paid', 1)
            ->assertJsonPath('currencies', ['NGN', 'USD']); // distinct, ordered
    }

    public function test_dashboard_and_settings_pages_render(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/app')->assertOk()->assertSee('Payables');
        $this->actingAs($user)->get('/settings')->assertOk()
            ->assertSee('Default currency')
            ->assertSee('API tokens');
    }

    public function test_settings_tab_is_selectable_by_query_param(): void
    {
        $user = User::factory()->create();

        // Default: the intake tab is active, Currency panel is hidden.
        $this->actingAs($user)->get('/settings')
            ->assertSee('data-panel="currency" class="hidden"', false);

        // ?tab=currency deep-links straight to the Currency panel (not hidden).
        $this->actingAs($user)->get('/settings?tab=currency')
            ->assertSee('data-panel="currency" class=""', false)
            ->assertSee('data-panel="intake" class="hidden"', false);
    }

    public function test_queue_filters_and_paginates(): void
    {
        $user = User::factory()->create();
        Invoice::factory()->for($user)->count(55)->create(['status' => Invoice::STATUS_NEEDS_REVIEW]);
        Invoice::factory()->for($user)->create(['status' => Invoice::STATUS_HELD]);

        // Page 1 caps at 50 with more to come.
        $this->actingAs($user)->getJson('/app/queue')
            ->assertOk()
            ->assertJsonCount(50, 'invoices')
            ->assertJsonPath('has_more', true)
            ->assertJsonPath('next_page', 2);

        // Status filter narrows it down (and scopes to this user).
        $this->actingAs($user)->getJson('/app/queue?status='.Invoice::STATUS_HELD)
            ->assertOk()
            ->assertJsonCount(1, 'invoices')
            ->assertJsonPath('has_more', false);
    }

    public function test_paid_invoice_links_to_its_paypal_transaction(): void
    {
        config(['handlemyap.paypal.env' => 'sandbox']);

        $user = User::factory()->create();
        $invoice = Invoice::factory()->for($user)->create(['status' => Invoice::STATUS_PAID]);
        Payout::factory()->for($user)->for($invoice)->create([
            'status' => Payout::STATUS_PAID,
            'rail' => 'paypal',
            'provider_ref' => 'ORDER-1',
            'provider_item_ref' => 'CAPTURE-9',
            'provider_status' => 'COMPLETED',
        ]);

        $this->actingAs($user)->getJson('/app/queue')
            ->assertOk()
            ->assertJsonPath('invoices.0.payment_url', 'https://www.sandbox.paypal.com/activity/payment/CAPTURE-9')
            ->assertJsonPath('invoices.0.payment_ref', 'CAPTURE-9')
            ->assertJsonPath('invoices.0.payment_demo', false);

        // Live env points at the production PayPal host.
        config(['handlemyap.paypal.env' => 'live']);
        $this->actingAs($user)->getJson('/app/queue')
            ->assertJsonPath('invoices.0.payment_url', 'https://www.paypal.com/activity/payment/CAPTURE-9');
    }

    public function test_demo_paid_invoice_has_no_payment_link(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->for($user)->create(['status' => Invoice::STATUS_PAID]);
        Payout::factory()->for($user)->for($invoice)->create([
            'status' => Payout::STATUS_PAID,
            'rail' => 'paypal',
            'provider_ref' => null,
            'provider_item_ref' => null,
            'provider_status' => 'DEMO_CAPTURE',
        ]);

        $this->actingAs($user)->getJson('/app/queue')
            ->assertOk()
            ->assertJsonPath('invoices.0.payment_url', null)
            ->assertJsonPath('invoices.0.payment_demo', true);
    }

    public function test_unpaid_invoice_has_no_payment_fields_set(): void
    {
        $user = User::factory()->create();
        Invoice::factory()->for($user)->create(['status' => Invoice::STATUS_NEEDS_REVIEW]);

        $this->actingAs($user)->getJson('/app/queue')
            ->assertOk()
            ->assertJsonPath('invoices.0.payment_url', null)
            ->assertJsonPath('invoices.0.payment_demo', false);
    }

    public function test_queue_search_matches_po_ref_and_amount(): void
    {
        $user = User::factory()->create();
        Invoice::factory()->for($user)->create(['vendor_name' => 'Alpha', 'po_ref' => 'PO-7741', 'amount' => 100]);
        Invoice::factory()->for($user)->create(['vendor_name' => 'Beta', 'po_ref' => 'PO-0002', 'amount' => 3282.00]);

        $this->actingAs($user)->getJson('/app/queue?search=7741')
            ->assertOk()->assertJsonCount(1, 'invoices')
            ->assertJsonPath('invoices.0.vendor', 'Alpha');

        $this->actingAs($user)->getJson('/app/queue?search=3282')
            ->assertOk()->assertJsonCount(1, 'invoices')
            ->assertJsonPath('invoices.0.vendor', 'Beta');
    }

    public function test_invoice_detail_page_shows_payment_link_for_paid_invoice(): void
    {
        config(['handlemyap.paypal.env' => 'sandbox']);

        $user = User::factory()->create();
        $invoice = Invoice::factory()->for($user)->create(['status' => Invoice::STATUS_PAID, 'vendor_name' => 'Blue Harbor']);
        Payout::factory()->for($user)->for($invoice)->create([
            'status' => Payout::STATUS_PAID, 'rail' => 'paypal',
            'provider_item_ref' => 'CAPTURE-42', 'provider_status' => 'COMPLETED',
        ]);

        $this->actingAs($user)->get("/app/invoices/{$invoice->id}")
            ->assertOk()
            ->assertSee('Blue Harbor')
            ->assertSee('Paid via PayPal')
            ->assertSee('https://www.sandbox.paypal.com/activity/payment/CAPTURE-42')
            ->assertSee('Remove payment');
    }

    public function test_invoice_detail_page_is_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $invoice = Invoice::factory()->for($owner)->create();

        $this->actingAs(User::factory()->create())
            ->get("/app/invoices/{$invoice->id}")
            ->assertForbidden();
    }

    public function test_remove_payment_deletes_payout_and_reopens_invoice(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->for($user)->create(['status' => Invoice::STATUS_PAID, 'approved_at' => now()]);
        $payout = Payout::factory()->for($user)->for($invoice)->create([
            'status' => Payout::STATUS_PAID, 'rail' => 'paypal', 'provider_status' => 'COMPLETED',
        ]);

        $this->actingAs($user)
            ->delete("/app/invoices/{$invoice->id}/payment")
            ->assertRedirect("/app/invoices/{$invoice->id}");

        $this->assertDatabaseMissing('payouts', ['id' => $payout->id]);
        $this->assertSame(Invoice::STATUS_NEEDS_REVIEW, $invoice->fresh()->status);
    }
}
