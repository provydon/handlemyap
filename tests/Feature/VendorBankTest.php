<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VendorBankTest extends TestCase
{
    use RefreshDatabase;

    private function fakeDwolla(): void
    {
        Http::fake([
            '*/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            '*/funding-sources' => Http::response(null, 201, [
                'Location' => 'https://api-sandbox.dwolla.com/funding-sources/fs-vendor-1',
            ]),
            '*/customers' => Http::response(null, 201, [
                'Location' => 'https://api-sandbox.dwolla.com/customers/cust-v1',
            ]),
        ]);
    }

    public function test_vendors_page_renders(): void
    {
        $user = User::factory()->create();
        $user->vendors()->create(['name' => 'Acme Supplies', 'email' => 'ap@acme.test', 'currency' => 'USD']);

        $this->actingAs($user)->get('/app/vendors')
            ->assertOk()->assertSee('Acme Supplies')->assertSee('Add bank');
    }

    public function test_adding_a_vendor_bank_registers_a_funding_source(): void
    {
        $this->fakeDwolla();
        $user = User::factory()->create();
        $vendor = $user->vendors()->create(['name' => 'Acme Supplies', 'email' => 'ap@acme.test', 'currency' => 'USD']);

        $this->actingAs($user)->post("/app/vendors/{$vendor->id}/bank", [
            'routing_number' => '222222226',
            'account_number' => '1234567890',
            'account_type' => 'checking',
        ])->assertRedirect();

        $this->assertSame('fs-vendor-1', $vendor->refresh()->dwolla_funding_source_id);
    }

    public function test_cannot_add_a_bank_to_another_users_vendor(): void
    {
        $this->fakeDwolla();
        $other = User::factory()->create();
        $vendor = $other->vendors()->create(['name' => 'Not Yours', 'email' => 'x@x.test', 'currency' => 'USD']);

        $this->actingAs(User::factory()->create())
            ->post("/app/vendors/{$vendor->id}/bank", [
                'routing_number' => '222222226', 'account_number' => '1234567890', 'account_type' => 'checking',
            ])->assertForbidden();

        $this->assertNull($vendor->refresh()->dwolla_funding_source_id);
    }

    public function test_vendor_bank_validates_routing_number(): void
    {
        $user = User::factory()->create();
        $vendor = $user->vendors()->create(['name' => 'Acme', 'email' => 'a@a.test', 'currency' => 'USD']);

        $this->actingAs($user)->post("/app/vendors/{$vendor->id}/bank", [
            'routing_number' => '123', 'account_number' => '1', 'account_type' => 'checking',
        ])->assertSessionHasErrors('routing_number');

        $this->assertNull($vendor->refresh()->dwolla_funding_source_id);
    }
}
