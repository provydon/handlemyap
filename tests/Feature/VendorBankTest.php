<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorBankTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendors_page_renders(): void
    {
        $user = User::factory()->create();
        $user->vendors()->create(['name' => 'Acme Supplies', 'email' => 'ap@acme.test', 'currency' => 'USD']);

        $this->actingAs($user)->get('/app/vendors')
            ->assertOk()->assertSee('Acme Supplies')->assertSee('Add PayPal');
    }

    public function test_adding_a_vendor_paypal_stores_the_destination(): void
    {
        $user = User::factory()->create();
        $vendor = $user->vendors()->create(['name' => 'Acme Supplies', 'email' => 'ap@acme.test', 'currency' => 'USD']);

        $this->actingAs($user)->post("/app/vendors/{$vendor->id}/bank", [
            'paypal_email' => 'Payouts@Acme.test',
        ])->assertRedirect();

        // Stored lower-cased as the PayPal rail's destination.
        $this->assertSame('payouts@acme.test', $vendor->refresh()->payoutMethod('paypal')?->recipient_ref);
    }

    public function test_cannot_add_a_payout_to_another_users_vendor(): void
    {
        $other = User::factory()->create();
        $vendor = $other->vendors()->create(['name' => 'Not Yours', 'email' => 'x@x.test', 'currency' => 'USD']);

        $this->actingAs(User::factory()->create())
            ->post("/app/vendors/{$vendor->id}/bank", [
                'paypal_email' => 'payouts@acme.test',
            ])->assertForbidden();

        $this->assertNull($vendor->refresh()->payoutMethod('paypal'));
    }

    public function test_vendor_paypal_validates_the_email(): void
    {
        $user = User::factory()->create();
        $vendor = $user->vendors()->create(['name' => 'Acme', 'email' => 'a@a.test', 'currency' => 'USD']);

        $this->actingAs($user)->post("/app/vendors/{$vendor->id}/bank", [
            'paypal_email' => 'not-an-email',
        ])->assertSessionHasErrors('paypal_email');

        $this->assertNull($vendor->refresh()->payoutMethod('paypal'));
    }
}
