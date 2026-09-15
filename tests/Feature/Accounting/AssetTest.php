<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\AssetCategory;
use App\Models\Accounting\DepreciationRun;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\FixedAsset;
use App\Models\Core\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class AssetTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.asset_categories.view',
            'accounting.asset_categories.create',
            'accounting.asset_categories.update',
            'accounting.asset_categories.delete',
            'accounting.assets.view',
            'accounting.assets.create',
            'accounting.assets.update',
            'accounting.assets.delete',
            'accounting.assets.dispose',
            'accounting.depreciation_runs.view',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeCategory(array $overrides = []): AssetCategory
    {
        return AssetCategory::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
        ], $overrides));
    }

    private function makeAsset(array $overrides = []): FixedAsset
    {
        $category = $this->makeCategory();
        $cost     = 10000.0;

        return FixedAsset::factory()->create(array_merge([
            'organization_id'          => $this->organization->id,
            'asset_category_id'        => $category->id,
            'acquisition_cost'         => $cost,
            'book_value'               => $cost,
            'accumulated_depreciation' => 0,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Asset Categories — CRUD
    // -------------------------------------------------------------------------

    public function test_categories_index_returns_list(): void
    {
        $this->makeCategory(['name' => 'Vehicles']);
        $this->makeCategory(['name' => 'Computers']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/asset-categories');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_categories_store_creates_category(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/asset-categories', [
                'name'                        => 'IT Equipment',
                'code'                        => 'ITE',
                'default_useful_life_years'   => 3,
                'default_depreciation_method' => 'straight_line',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'IT Equipment')
            ->assertJsonPath('data.code', 'ITE');
    }

    public function test_categories_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/asset-categories', []);

        $response->assertStatus(422);
    }

    public function test_categories_show_returns_category(): void
    {
        $category = $this->makeCategory();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/asset-categories/' . $category->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $category->id);
    }

    public function test_categories_update_modifies_category(): void
    {
        $category = $this->makeCategory(['name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/asset-categories/' . $category->uuid, [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');
    }

    public function test_categories_destroy_deletes_category(): void
    {
        $category = $this->makeCategory();

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/asset-categories/' . $category->uuid);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('asset_categories', ['id' => $category->id]);
    }

    // -------------------------------------------------------------------------
    // Fixed Assets — CRUD
    // -------------------------------------------------------------------------

    public function test_assets_index_returns_paginated_list(): void
    {
        $this->makeAsset();
        $this->makeAsset();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/assets');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_assets_store_creates_asset(): void
    {
        $category = $this->makeCategory();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/assets', [
                'asset_category_id' => $category->id,
                'name'              => 'Company Laptop',
                'acquisition_date'  => '2025-01-15',
                'acquisition_cost'  => 3500.00,
                'useful_life_years' => 3,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Company Laptop');
    }

    public function test_assets_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/assets', []);

        $response->assertStatus(422);
    }

    public function test_assets_show_returns_asset(): void
    {
        $asset = $this->makeAsset();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/assets/' . $asset->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $asset->id);
    }

    public function test_assets_update_modifies_asset(): void
    {
        $asset = $this->makeAsset(['name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/assets/' . $asset->uuid, [
                'name' => 'Updated Name',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_assets_update_rejects_disposed_asset(): void
    {
        $asset = $this->makeAsset(['status' => FixedAsset::STATUS_DISPOSED]);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/assets/' . $asset->uuid, ['name' => 'New Name']);

        $response->assertStatus(400);
    }

    public function test_assets_destroy_deletes_undepreciated_asset(): void
    {
        $asset = $this->makeAsset(['accumulated_depreciation' => 0]);

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/assets/' . $asset->uuid);

        $response->assertStatus(200);
        $this->assertSoftDeleted('fixed_assets', ['id' => $asset->id]);
    }

    public function test_assets_destroy_rejects_depreciated_asset(): void
    {
        $asset = $this->makeAsset(['accumulated_depreciation' => 1000]);

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/assets/' . $asset->uuid);

        $response->assertStatus(400);
    }

    // -------------------------------------------------------------------------
    // Depreciation schedule
    // -------------------------------------------------------------------------

    public function test_schedule_returns_depreciation_periods(): void
    {
        $asset = $this->makeAsset([
            'acquisition_cost'  => 12000,
            'book_value'        => 12000,
            'salvage_value'     => 0,
            'useful_life_years' => 3,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/assets/' . $asset->uuid . '/schedule');

        $response->assertStatus(200)
            ->assertJsonPath('data.asset_id', $asset->id);

        $this->assertNotEmpty($response->json('data.schedule'));
    }

    // -------------------------------------------------------------------------
    // Only own-organization assets visible
    // -------------------------------------------------------------------------

    public function test_index_excludes_other_org_assets(): void
    {
        $otherOrg      = \App\Models\Core\Organization::factory()->create();
        $otherCategory = AssetCategory::factory()->create(['organization_id' => $otherOrg->id]);
        FixedAsset::factory()->create([
            'organization_id'  => $otherOrg->id,
            'asset_category_id' => $otherCategory->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/assets');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Filters
    // -------------------------------------------------------------------------

    public function test_assets_index_filters_by_status_category_and_search(): void
    {
        $laptop   = $this->makeAsset(['name' => 'Laptop Pro', 'serial_number' => 'SN-111']);
        $forklift = $this->makeAsset(['name' => 'Forklift', 'status' => FixedAsset::STATUS_DISPOSED]);

        $ids = fn (string $query): array => array_column(
            $this->withToken($this->token)->getJson('/api/v1/assets' . $query)->assertStatus(200)->json('data'),
            'id'
        );

        $this->assertSame([$forklift->id], $ids('?status=' . FixedAsset::STATUS_DISPOSED));
        $this->assertSame([$laptop->id], $ids('?category_id=' . $laptop->asset_category_id));
        $this->assertSame([$laptop->id], $ids('?search=SN-111'));

        $this->withToken($this->token)
            ->getJson('/api/v1/assets?per_page=1')
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_categories_index_filters_by_active_and_search(): void
    {
        $this->makeCategory(['name' => 'Current Equipment', 'is_active' => true]);
        $this->makeCategory(['name' => 'Retired Equipment', 'is_active' => false]);

        $names = fn (string $query): array => array_column(
            $this->withToken($this->token)->getJson('/api/v1/asset-categories' . $query)->assertStatus(200)->json('data'),
            'name'
        );

        $this->assertSame(['Retired Equipment'], $names('?active=false'));
        $this->assertSame(['Current Equipment'], $names('?active=true'));
        $this->assertSame(['Retired Equipment'], $names('?search=Retired'));

        $this->withToken($this->token)
            ->getJson('/api/v1/asset-categories')
            ->assertJsonPath('meta.per_page', 50);
    }

    public function test_categories_store_rejects_a_code_already_used_in_the_organization(): void
    {
        $this->makeCategory(['code' => 'DUP']);

        $otherOrg = Organization::factory()->create();
        AssetCategory::factory()->create(['organization_id' => $otherOrg->id, 'code' => 'ELSEWHERE']);

        $this->withToken($this->token)
            ->postJson('/api/v1/asset-categories', ['name' => 'Duplicate', 'code' => 'DUP'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'DUPLICATE_CODE')
            ->assertJsonPath('error.message', "Category code 'DUP' already exists.");

        $this->withToken($this->token)
            ->postJson('/api/v1/asset-categories', ['name' => 'Reused elsewhere', 'code' => 'ELSEWHERE'])
            ->assertStatus(201);
    }

    public function test_depreciation_runs_index_filters_and_excludes_other_organizations(): void
    {
        $fiscalYear = FiscalYear::factory()->create([
            'organization_id' => $this->organization->id,
            'name'            => 'FY 2025',
            'start_date'      => '2025-01-01',
            'end_date'        => '2025-12-31',
        ]);
        $pending = $this->makeDepreciationRun($this->organization->id, $fiscalYear->id, '2025-01-31', DepreciationRun::STATUS_PENDING);
        $posted  = $this->makeDepreciationRun($this->organization->id, $fiscalYear->id, '2025-02-28', DepreciationRun::STATUS_POSTED);

        $otherOrg = Organization::factory()->create();
        $otherFy  = FiscalYear::factory()->create([
            'organization_id' => $otherOrg->id,
            'name'            => 'FY 2025',
            'start_date'      => '2025-01-01',
            'end_date'        => '2025-12-31',
        ]);
        $this->makeDepreciationRun($otherOrg->id, $otherFy->id, '2025-03-31', DepreciationRun::STATUS_PENDING);

        $ids = fn (string $query): array => array_column(
            $this->withToken($this->token)->getJson('/api/v1/depreciation-runs' . $query)->assertStatus(200)->json('data'),
            'id'
        );

        $this->assertSame([$posted->id, $pending->id], $ids(''));
        $this->assertSame([$pending->id], $ids('?status=' . DepreciationRun::STATUS_PENDING));
        $this->assertSame([$posted->id, $pending->id], $ids('?fiscal_year_id=' . $fiscalYear->id));

        $this->withToken($this->token)
            ->getJson('/api/v1/depreciation-runs')
            ->assertJsonPath('data.0.fiscal_year.name', 'FY 2025')
            ->assertJsonPath('meta.per_page', 20);
    }

    // -------------------------------------------------------------------------
    // AuC settlement
    // -------------------------------------------------------------------------

    public function test_settle_auc_rejects_another_organizations_target(): void
    {
        $source        = $this->makeAsset();
        $otherOrg      = Organization::factory()->create();
        $otherCategory = AssetCategory::factory()->create(['organization_id' => $otherOrg->id]);
        $target        = FixedAsset::factory()->create([
            'organization_id'   => $otherOrg->id,
            'asset_category_id' => $otherCategory->id,
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v1/assets/' . $source->uuid . '/settle-auc', [
                'target_asset_id' => $target->id,
                'amount'          => 100,
                'settlement_date' => '2025-06-30',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('target_asset_id');
    }

    public function test_settle_auc_rejects_a_source_that_is_not_auc(): void
    {
        $source = $this->makeAsset();
        $target = $this->makeAsset();

        $this->withToken($this->token)
            ->postJson('/api/v1/assets/' . $source->uuid . '/settle-auc', [
                'target_asset_id' => $target->id,
                'amount'          => 100,
                'settlement_date' => '2025-06-30',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'SETTLEMENT_FAILED');
    }

    private function makeDepreciationRun(int $organizationId, int $fiscalYearId, string $runDate, string $status): DepreciationRun
    {
        return DepreciationRun::create([
            'organization_id' => $organizationId,
            'fiscal_year_id'  => $fiscalYearId,
            'run_date'        => $runDate,
            'period_start'    => substr($runDate, 0, 8) . '01',
            'period_end'      => $runDate,
            'status'          => $status,
        ]);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/assets')->assertStatus(401);
        $this->getJson('/api/v1/asset-categories')->assertStatus(401);
    }
}
