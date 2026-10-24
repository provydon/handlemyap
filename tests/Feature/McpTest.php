<?php

namespace Tests\Feature;

use App\Models\AutoApprovalRule;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpTest extends TestCase
{
    use RefreshDatabase;

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    /** @param array<string, mixed> $body */
    private function rpc(array $body, ?string $token = null)
    {
        $headers = $token ? ['Authorization' => "Bearer {$token}"] : [];

        return $this->postJson('/mcp', $body, $headers);
    }

    public function test_initialize_needs_no_auth_and_returns_server_info(): void
    {
        $this->rpc(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize'])
            ->assertOk()
            ->assertJsonPath('result.serverInfo.name', 'handlemyap')
            ->assertJsonPath('jsonrpc', '2.0');
    }

    public function test_tools_list_requires_a_token(): void
    {
        $this->rpc(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'])
            ->assertOk()
            ->assertJsonPath('error.code', -32001);
    }

    public function test_tools_list_returns_the_five_tools(): void
    {
        $user = User::factory()->create();

        $this->rpc(['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/list'], $this->token($user))
            ->assertOk()
            ->assertJsonPath('result.tools.0.name', 'submit_invoice')
            ->assertJsonCount(5, 'result.tools');
    }

    public function test_submit_invoice_tool_parks_unknown_vendor_for_review(): void
    {
        $user = User::factory()->create();

        $response = $this->rpc([
            'jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call',
            'params' => [
                'name' => 'submit_invoice',
                'arguments' => [
                    'vendor_name' => 'Jane Q Notary',
                    'vendor_email' => 'jane@example.test',
                    'amount' => 125.00,
                    'external_ref' => 'order:12345:payment',
                ],
            ],
        ], $this->token($user))->assertOk();

        $response->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.status', Invoice::STATUS_NEEDS_REVIEW);

        $this->assertDatabaseHas('invoices', [
            'user_id' => $user->id,
            'source' => 'mcp',
            'external_ref' => 'order:12345:payment',
        ]);
    }

    public function test_submit_invoice_auto_pays_when_rules_allow(): void
    {
        $user = User::factory()->create();
        AutoApprovalRule::create([
            'user_id' => $user->id,
            'enabled' => true,
            'max_amount' => 500,
            'known_vendors_only' => false,
            'block_duplicates' => true,
        ]);

        $this->rpc([
            'jsonrpc' => '2.0', 'id' => 5, 'method' => 'tools/call',
            'params' => [
                'name' => 'submit_invoice',
                'arguments' => [
                    'vendor_name' => 'Acme Supplies',
                    'vendor_email' => 'ap@acme.test',
                    'amount' => 90.00,
                ],
            ],
        ], $this->token($user))
            ->assertOk()
            ->assertJsonPath('result.structuredContent.status', Invoice::STATUS_PAID);
    }

    public function test_submit_invoice_is_idempotent_on_external_ref(): void
    {
        $user = User::factory()->create();
        $token = $this->token($user);

        $args = [
            'name' => 'submit_invoice',
            'arguments' => ['vendor_name' => 'Dup Co', 'amount' => 10, 'external_ref' => 'ref:dup'],
        ];

        $this->rpc(['jsonrpc' => '2.0', 'id' => 6, 'method' => 'tools/call', 'params' => $args], $token)->assertOk();
        $this->rpc(['jsonrpc' => '2.0', 'id' => 7, 'method' => 'tools/call', 'params' => $args], $token)->assertOk();

        $this->assertSame(1, Invoice::where('external_ref', 'ref:dup')->count());
    }

    public function test_docs_page_renders(): void
    {
        $this->get('/docs')->assertOk()->assertSee('MCP server');
    }

    public function test_mcp_get_returns_info(): void
    {
        $this->get('/mcp')->assertOk()->assertJsonPath('name', 'handlemyap');
    }
}
