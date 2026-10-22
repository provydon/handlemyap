<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_cannot_touch_another_users_invoice(): void
    {
        $other = User::factory()->create();
        $invoice = Invoice::factory()->for($other)->create();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/invoices/{$invoice->id}")->assertNotFound();
        $this->postJson("/api/v1/invoices/{$invoice->id}/approve")->assertNotFound();
    }
}
