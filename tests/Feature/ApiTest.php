<?php

namespace Tests\Feature;

use App\Jobs\ExtractInvoice;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/invoices')->assertUnauthorized();
    }

    public function test_submits_a_structured_invoice_and_sees_it(): void
    {
        Sanctum::actingAs($user = User::factory()->create());

        $this->postJson('/api/v1/invoices', [
            'vendor_name' => 'Jane Q Notary',
            'vendor_email' => 'jane@example.com',
            'amount' => 125.00,
            'currency' => 'USD',
            'due_date' => now()->toDateString(),
            'external_ref' => 'order:12345:notary-payment', // NotaryDash-style idempotency key
        ])->assertCreated()->assertJsonPath('vendor_name', 'Jane Q Notary');

        $this->getJson('/api/v1/invoices')->assertOk()->assertJsonCount(1);

        // Idempotent: same external_ref doesn't create a second invoice.
        $this->postJson('/api/v1/invoices', [
            'vendor_name' => 'Jane Q Notary', 'amount' => 125.00, 'currency' => 'USD',
            'external_ref' => 'order:12345:notary-payment',
        ])->assertCreated();
        $this->assertSame(1, $user->invoices()->count());
    }

    public function test_approve_triggers_a_payout(): void
    {
        Sanctum::actingAs($user = User::factory()->create());

        $create = $this->postJson('/api/v1/invoices', [
            'vendor_name' => 'Acme', 'vendor_email' => 'pay@acme.test',
            'amount' => 300, 'currency' => 'USD', 'due_date' => now()->toDateString(),
        ])->assertCreated()->json();

        $this->assertSame(Invoice::STATUS_NEEDS_REVIEW, $create['status']); // no rule → review

        $this->postJson("/api/v1/invoices/{$create['id']}/approve")
            ->assertOk()
            ->assertJsonPath('invoice.status', Invoice::STATUS_PAID)
            ->assertJsonPath('payout.status', 'paid');
    }

    public function test_source_defaults_to_api_but_can_be_labelled(): void
    {
        Sanctum::actingAs($user = User::factory()->create());

        // No source → 'api' (an external integrator).
        $this->postJson('/api/v1/invoices', [
            'vendor_name' => 'Acme', 'amount' => 10, 'currency' => 'USD',
        ])->assertCreated()->assertJsonPath('source', 'api');

        // The dashboard dogfoods this endpoint and labels its own entries.
        $this->postJson('/api/v1/invoices', [
            'vendor_name' => 'Beta', 'amount' => 10, 'currency' => 'USD', 'source' => 'manual',
        ])->assertCreated()->assertJsonPath('source', 'manual');

        // An unknown source falls back to 'api' rather than being stored verbatim.
        $this->postJson('/api/v1/invoices', [
            'vendor_name' => 'Gamma', 'amount' => 10, 'currency' => 'USD', 'source' => 'bogus',
        ])->assertCreated()->assertJsonPath('source', 'api');
    }

    public function test_deferred_upload_stores_instantly_and_queues_the_ai_read(): void
    {
        Storage::fake('local');
        Queue::fake();
        Sanctum::actingAs(User::factory()->create());

        // An invoice that pays via PayPal, in NGN, with line items.
        $doc = json_encode([
            'vendor' => 'Lagos Supplies Ltd',
            'amount' => 150000,
            'currency' => 'NGN',
            'due_date' => now()->addDays(10)->toDateString(),
            'paypal_email' => 'pay@lagossupplies.ng',
            'line_items' => [
                ['description' => 'Printer paper', 'qty' => 10, 'unit_price' => 5000],
                ['description' => 'Toner', 'qty' => 2, 'unit_price' => 50000],
            ],
        ]);
        $file = UploadedFile::fake()->createWithContent('invoice.json', $doc);

        // Deferred → instant "extracting", file is stored, AI read is queued.
        $created = $this->post('/api/v1/invoices', ['file' => $file, 'defer' => '1'], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('status', Invoice::STATUS_EXTRACTING)
            ->assertJsonPath('source', 'api')
            ->json();
        Storage::disk('local')->assertExists($created['file_path']);
        Queue::assertPushed(ExtractInvoice::class);

        // Running the read (as the background worker would) fills amount /
        // currency / due and makes the PayPal email the payout address.
        $this->postJson("/api/v1/invoices/{$created['id']}/extract")
            ->assertOk()
            ->assertJsonPath('currency', 'NGN')
            ->assertJsonPath('amount', '150000.00')
            ->assertJsonPath('vendor.email', 'pay@lagossupplies.ng');
    }

    public function test_missing_currency_falls_back_to_account_default(): void
    {
        Storage::fake('local');
        Sanctum::actingAs(User::factory()->create(['default_currency' => 'EUR']));

        $file = UploadedFile::fake()->createWithContent('inv.json', json_encode([
            'vendor' => 'No Currency Co', 'amount' => 50, // no currency on the doc
        ]));

        // Synchronous (no defer) so the read runs in-request.
        $this->post('/api/v1/invoices', ['file' => $file], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('currency', 'EUR');
    }

    public function test_cannot_touch_another_users_invoice(): void
    {
        $other = User::factory()->create();
        $invoice = Invoice::factory()->for($other)->create();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/invoices/{$invoice->id}")->assertNotFound();
        $this->postJson("/api/v1/invoices/{$invoice->id}/approve")->assertNotFound();
    }
}
