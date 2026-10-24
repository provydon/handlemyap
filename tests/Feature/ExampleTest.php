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
        $this->get('/')->assertOk()->assertSee('Your vendors send the invoices');
    }

    public function test_user_can_register_and_is_signed_in(): void
    {
        $this->post('/register', [
            'name' => 'Al', 'email' => 'al@example.test',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect('/app');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'al@example.test']);
    }

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sign in');
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
