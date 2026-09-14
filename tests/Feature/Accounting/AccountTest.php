<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class AccountTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.accounts.view',
            'accounting.accounts.create',
            'accounting.accounts.update',
            'accounting.accounts.delete',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeAccount(array $overrides = []): Account
    {
        return Account::create(array_merge([
            'organization_id' => $this->organization->id,
            'code'            => 'AC-' . fake()->unique()->numerify('####'),
            'name'            => 'Test Account',
            'account_type'    => 'asset',
            'sub_type'        => 'bank',
            'is_active'       => true,
            'is_header'       => false,
            'level'           => 1,
            'path'            => 'AC-' . fake()->unique()->numerify('####'),
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_tree_nests_children_and_filters_top_level_accounts(): void
    {
        $assets = $this->makeAccount(['code' => '1000', 'is_header' => true]);
        $this->makeAccount(['code' => '1001', 'parent_id' => $assets->id, 'level' => 2]);
        $this->makeAccount(['code' => '4000', 'account_type' => 'income', 'sub_type' => 'sales', 'is_active' => false]);

        $codes = fn (string $query): array => array_column(
            $this->withToken($this->token)->getJson("/api/v1/accounts?{$query}")->json('data'),
            'code'
        );

        $this->withToken($this->token)->getJson('/api/v1/accounts')
            ->assertStatus(200)
            ->assertJsonPath('data.0.code', '1000')
            ->assertJsonPath('data.0.children.0.code', '1001')
            ->assertJsonPath('data.1.code', '4000');

        $this->assertSame(['4000'], $codes('type=income'));
        $this->assertSame(['1000'], $codes('active_only=1'));
    }

    public function test_flat_applies_filters_and_lists_only_the_dropdown_columns(): void
    {
        $this->makeAccount(['code' => '1001']);
        $this->makeAccount(['code' => '1000', 'is_header' => true]);
        $this->makeAccount(['code' => '1002', 'is_active' => false]);
        $this->makeAccount(['code' => '4001', 'account_type' => 'income', 'sub_type' => 'sales']);

        $codes = fn (string $query): array => array_column(
            $this->withToken($this->token)->getJson("/api/v1/accounts/flat?{$query}")->json('data'),
            'code'
        );

        $this->assertSame(['1000', '1001', '1002', '4001'], $codes(''));
        $this->assertSame(['4001'], $codes('type=income'));
        $this->assertSame(['1001', '4001'], $codes('postable=1'));
        $this->assertSame(['1000', '1001', '4001'], $codes('active_only=1'));

        $row = $this->withToken($this->token)->getJson('/api/v1/accounts/flat')->json('data.0');
        $this->assertSame(['id', 'code', 'name', 'account_type', 'sub_type', 'is_header', 'is_active'], array_keys($row));
    }

    public function test_store_under_a_parent_derives_level_and_path(): void
    {
        $parent = $this->makeAccount(['code' => '1000', 'path' => '1000', 'level' => 1, 'is_header' => true]);

        $this->withToken($this->token)
            ->postJson('/api/v1/accounts', [
                'parent_id'    => $parent->id,
                'code'         => '1010',
                'name'         => 'Petty Cash',
                'account_type' => 'asset',
                'sub_type'     => 'cash',
            ])
            ->assertStatus(201)
            ->assertJsonPath('message', 'Account created successfully')
            ->assertJsonPath('data.level', 2)
            ->assertJsonPath('data.path', '1000.1010')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.organization_id', $this->organization->id);
    }

    public function test_destroy_reports_why_an_account_cannot_be_deleted(): void
    {
        $parent = $this->makeAccount(['code' => '2000']);
        $this->makeAccount(['code' => '2001', 'parent_id' => $parent->id, 'level' => 2]);
        $system = $this->makeAccount(['code' => '2100', 'is_system' => true]);

        $this->withToken($this->token)->deleteJson('/api/v1/accounts/' . $parent->id)
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'HAS_CHILDREN')
            ->assertJsonPath('error.message', 'Cannot delete account with child accounts');

        $this->withToken($this->token)->deleteJson('/api/v1/accounts/' . $system->id)
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'SYSTEM_ACCOUNT')
            ->assertJsonPath('error.message', 'System accounts cannot be deleted');
    }

    public function test_update_system_account_reports_system_account(): void
    {
        $system = $this->makeAccount(['is_system' => true]);

        $this->withToken($this->token)->putJson('/api/v1/accounts/' . $system->id, ['name' => 'Renamed'])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'SYSTEM_ACCOUNT')
            ->assertJsonPath('error.message', 'System accounts cannot be modified');
    }

    public function test_index_returns_tree(): void
    {
        $this->makeAccount();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/accounts');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Flat
    // -------------------------------------------------------------------------

    public function test_flat_returns_list(): void
    {
        $this->makeAccount();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/accounts/flat');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/accounts', []);

        $response->assertStatus(422);
    }

    public function test_store_creates_account(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/accounts', [
                'code'         => 'CASH-001',
                'name'         => 'Cash on Hand',
                'account_type' => 'asset',
                'sub_type'     => 'cash',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_store_rejects_duplicate_code(): void
    {
        $account = $this->makeAccount(['code' => 'DUP-001']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/accounts', [
                'code'         => 'DUP-001',
                'name'         => 'Duplicate',
                'account_type' => 'asset',
                'sub_type'     => 'cash',
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_details(): void
    {
        $account = $this->makeAccount();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/accounts/' . $account->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/accounts/999999');

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_update_modifies_account(): void
    {
        $account = $this->makeAccount(['name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/accounts/' . $account->id, [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('New Name', $account->fresh()->name);
    }

    public function test_update_rejects_system_account(): void
    {
        $account = $this->makeAccount(['is_system' => true]);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/accounts/' . $account->id, [
                'name' => 'New Name',
            ]);

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_account(): void
    {
        $account = $this->makeAccount();

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/accounts/' . $account->id);

        $response->assertStatus(200);
        $this->assertNull(Account::find($account->id));
    }

    public function test_destroy_rejects_system_account(): void
    {
        $account = $this->makeAccount(['is_system' => true]);

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/accounts/' . $account->id);

        $response->assertStatus(400);
    }

    // -------------------------------------------------------------------------
    // Ledger
    // -------------------------------------------------------------------------

    public function test_ledger_returns_data(): void
    {
        $account = $this->makeAccount();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/accounts/' . $account->id . '/ledger');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/accounts')->assertStatus(401);
    }
}
