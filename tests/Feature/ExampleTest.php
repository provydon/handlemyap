<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_renders(): void
    {
        $this->get('/')->assertOk()->assertSee('pays them');
    }

    public function test_demo_login_signs_in(): void
    {
        User::factory()->create(['email' => 'demo@handlemyap.test']);

        $this->post('/demo-login')->assertRedirect('/app');
        $this->assertAuthenticated();
    }

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee('handle');
    }

    public function test_dashboard_requires_auth(): void
    {
        $this->get('/app')->assertRedirect('/login');
    }

    public function test_authed_user_sees_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/app')->assertOk()->assertSee('Payables');
    }
}
