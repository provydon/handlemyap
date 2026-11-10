<?php

namespace Tests\Feature;

use App\Models\Invoice;
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
}
