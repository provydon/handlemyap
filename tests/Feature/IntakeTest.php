<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IntakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_intake_page_renders_for_a_handle(): void
    {
        User::factory()->create(['handle' => 'acme', 'name' => 'Acme Co']);

        $this->get('/u/acme')->assertOk()->assertSee('Send Acme Co an invoice');
    }

    public function test_unknown_handle_is_404(): void
    {
        $this->get('/u/nobody')->assertNotFound();
    }

    public function test_vendor_can_submit_an_invoice_into_the_owners_queue(): void
    {
        $owner = User::factory()->create(['handle' => 'acme']);

        $this->post('/u/acme', [
            'vendor_name' => 'Bob the Contractor',
            'vendor_email' => 'bob@example.test',
            'amount' => 90.00,
            'currency' => 'USD',
        ])->assertRedirect()->assertSessionHas('sent');

        $this->assertDatabaseHas('invoices', [
            'user_id' => $owner->id,
            'vendor_name' => 'Bob the Contractor',
            'source' => 'intake',
            'status' => Invoice::STATUS_NEEDS_REVIEW, // unknown vendor, no rule → safe
        ]);
    }

    public function test_vendor_can_upload_an_invoice_file(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['handle' => 'acme']);

        $file = UploadedFile::fake()->createWithContent(
            'invoice.txt',
            "Vendor: Bob Co\nEmail: bob@bob.test\nAmount: 75.00 USD\n",
        );

        $this->post('/u/acme', ['file' => $file])
            ->assertRedirect()->assertSessionHas('sent');

        $this->assertDatabaseHas('invoices', [
            'user_id' => $owner->id,
            'source' => 'intake',
            'vendor_name' => 'Bob Co',
        ]);
    }

    public function test_handle_can_be_updated_in_settings(): void
    {
        $user = User::factory()->create(['handle' => 'old']);

        $this->actingAs($user)->post('/settings/handle', ['handle' => 'new-handle'])
            ->assertRedirect();

        $this->assertSame('new-handle', $user->refresh()->handle);
    }
}
