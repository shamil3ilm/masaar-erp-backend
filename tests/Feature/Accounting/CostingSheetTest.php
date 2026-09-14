<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\CostingSheet;
use App\Models\Core\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CostingSheetTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.costing-sheets.manage',
            'accounting.costing-sheets.view',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeSheet(array $overrides = []): CostingSheet
    {
        return CostingSheet::create(array_merge([
            'organization_id' => $this->organization->id,
            'code'            => 'CS-' . fake()->unique()->numerify('####'),
            'name'            => 'Test Costing Sheet',
            'is_active'       => true,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_list(): void
    {
        $this->makeSheet();
        $this->makeSheet();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/costing-sheets');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_returns_empty_initially(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/costing-sheets');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_creates_costing_sheet(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/costing-sheets', [
                'code' => 'CS-MAIN',
                'name' => 'Main Costing Sheet',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'CS-MAIN');
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/costing-sheets', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_sheet_details(): void
    {
        $sheet = $this->makeSheet();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/costing-sheets/' . $sheet->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $sheet->id);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/costing-sheets/99999');

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_update_modifies_sheet(): void
    {
        $sheet = $this->makeSheet(['name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/costing-sheets/' . $sheet->id, [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_destroy_soft_deletes_sheet(): void
    {
        $sheet = $this->makeSheet();

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/costing-sheets/' . $sheet->id);

        $response->assertStatus(200);
        $this->assertSoftDeleted('costing_sheets', ['id' => $sheet->id]);
    }

    // -------------------------------------------------------------------------
    // Rows
    // -------------------------------------------------------------------------

    public function test_rows_returns_empty_for_new_sheet(): void
    {
        $sheet = $this->makeSheet();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/costing-sheets/' . $sheet->id . '/rows');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEmpty($response->json('data'));
    }

    public function test_add_row_creates_row(): void
    {
        $sheet = $this->makeSheet();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/costing-sheets/' . $sheet->id . '/rows', [
                'row_type'    => 'base',
                'description' => 'Material Costs',
                'sort_order'  => 10,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.row_type', 'base');
    }

    public function test_add_row_validates_required_fields(): void
    {
        $sheet = $this->makeSheet();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/costing-sheets/' . $sheet->id . '/rows', []);

        $response->assertStatus(422);
    }

    public function test_rows_lists_the_sheet_rows(): void
    {
        $sheet = $this->makeSheet();

        foreach (['Material Costs', 'Labour Costs'] as $description) {
            $this->withToken($this->token)
                ->postJson('/api/v1/costing-sheets/' . $sheet->id . '/rows', [
                    'row_type'    => 'base',
                    'description' => $description,
                ])
                ->assertStatus(201);
        }

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/costing-sheets/' . $sheet->id . '/rows');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.costing_sheet_id', $sheet->id);
    }

    // -------------------------------------------------------------------------
    // Filters and tenant isolation
    // -------------------------------------------------------------------------

    public function test_index_filters_by_search_and_active_only(): void
    {
        $this->makeSheet(['code' => 'CS-ALPHA', 'name' => 'Alpha', 'is_active' => true]);
        $this->makeSheet(['code' => 'CS-BETA', 'name' => 'Beta', 'is_active' => false]);
        $this->makeSheet(['code' => 'CS-GAMMA', 'name' => 'Gamma', 'is_active' => true]);

        $codes = fn (string $query): array => array_column(
            $this->withToken($this->token)->getJson('/api/v1/costing-sheets' . $query)->assertStatus(200)->json('data'),
            'code'
        );

        $this->assertSame(['CS-ALPHA', 'CS-BETA', 'CS-GAMMA'], $codes(''));
        $this->assertSame(['CS-ALPHA', 'CS-GAMMA'], $codes('?active_only=1'));
        $this->assertSame(['CS-BETA'], $codes('?search=Beta'));

        $this->withToken($this->token)
            ->getJson('/api/v1/costing-sheets?per_page=1')
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_another_organizations_sheet_is_not_found(): void
    {
        $otherOrg = Organization::factory()->create();
        $sheet    = $this->makeSheet(['organization_id' => $otherOrg->id]);
        $url      = '/api/v1/costing-sheets/' . $sheet->id;

        $this->withToken($this->token)->getJson($url)->assertStatus(404);
        $this->withToken($this->token)->putJson($url, ['name' => 'Taken'])->assertStatus(404);
        $this->withToken($this->token)->deleteJson($url)->assertStatus(404);
        $this->withToken($this->token)->getJson($url . '/rows')->assertStatus(404);
        $this->withToken($this->token)->postJson($url . '/rows', [])->assertStatus(404);
        $this->withToken($this->token)->postJson($url . '/run', [])->assertStatus(404);

        $this->assertNotSoftDeleted($sheet);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/costing-sheets')->assertStatus(401);
    }
}
