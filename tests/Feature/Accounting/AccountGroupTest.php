<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\AccountGroup;
use App\Models\Core\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class AccountGroupTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.account-groups.manage',
            'accounting.account-groups.view',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeGroup(array $overrides = []): AccountGroup
    {
        return AccountGroup::create(array_merge([
            'organization_id'  => $this->organization->id,
            'code'             => 'AG-' . fake()->unique()->numerify('###'),
            'name'             => 'Test Account Group',
            'account_category' => 'balance_sheet',
            'is_active'        => true,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_list(): void
    {
        $this->makeGroup();
        $this->makeGroup();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/account-groups');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_creates_account_group(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/account-groups', [
                'code'             => 'BS01',
                'name'             => 'Balance Sheet Group',
                'account_category' => 'balance_sheet',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/account-groups', []);

        $response->assertStatus(422);
    }

    public function test_store_validates_account_category_enum(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/account-groups', [
                'code'             => 'XX',
                'name'             => 'Test',
                'account_category' => 'invalid_category',
            ]);

        $response->assertStatus(422);
    }

    public function test_store_rejects_duplicate_code(): void
    {
        $this->makeGroup(['code' => 'DUP01']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/account-groups', [
                'code'             => 'DUP01',
                'name'             => 'Duplicate',
                'account_category' => 'balance_sheet',
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_group_details(): void
    {
        $group = $this->makeGroup();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/account-groups/' . $group->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/account-groups/' . fake()->uuid());

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_update_modifies_group(): void
    {
        $group = $this->makeGroup(['name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/account-groups/' . $group->uuid, [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('New Name', $group->fresh()->name);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_group(): void
    {
        $group = $this->makeGroup();

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/account-groups/' . $group->uuid);

        $response->assertStatus(200);
        $this->assertNull(AccountGroup::find($group->id));
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/account-groups')->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Filters, tenant isolation and response shape
    // -------------------------------------------------------------------------

    public function test_index_filters_by_active_only_and_category(): void
    {
        $this->makeGroup(['code' => 'A1']);
        $this->makeGroup(['code' => 'A2', 'is_active' => false]);
        $this->makeGroup(['code' => 'A3', 'account_category' => 'profit_loss']);
        $this->makeGroup(['code' => 'A4', 'organization_id' => Organization::factory()->create()->id]);

        $all = $this->withToken($this->token)->getJson('/api/v1/account-groups');
        $all->assertStatus(200)->assertJsonPath('meta.per_page', 20);
        $this->assertSame(['A1', 'A2', 'A3'], array_column($all->json('data'), 'code'));

        $active = $this->withToken($this->token)->getJson('/api/v1/account-groups?active_only=1');
        $this->assertSame(['A1', 'A3'], array_column($active->json('data'), 'code'));

        $category = $this->withToken($this->token)
            ->getJson('/api/v1/account-groups?account_category=profit_loss&per_page=2');
        $category->assertJsonPath('meta.per_page', 2);
        $this->assertSame(['A3'], array_column($category->json('data'), 'code'));
    }

    public function test_store_sets_the_organization(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/account-groups', [
                'code'             => 'PL01',
                'name'             => 'P&L Group',
                'account_category' => 'profit_loss',
            ])
            ->assertStatus(201)
            ->assertJsonPath('message', 'Account group created.')
            ->assertJsonPath('data.code', 'PL01')
            ->assertJsonPath('data.organization_id', $this->organization->id);
    }

    public function test_show_returns_404_for_other_organization_group(): void
    {
        $other = $this->makeGroup(['organization_id' => Organization::factory()->create()->id]);

        $this->withToken($this->token)
            ->getJson('/api/v1/account-groups/' . $other->getRouteKey())
            ->assertStatus(404);
    }
}
