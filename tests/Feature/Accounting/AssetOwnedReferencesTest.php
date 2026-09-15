<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\FixedAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\ChecksOwnedReferences;
use Tests\Traits\TestHelpers;

/**
 * Asset, depreciation and lease requests accept only the caller's
 * organization's categories, branches, assets, accounts and fiscal years.
 */
class AssetOwnedReferencesTest extends TestCase
{
    use ChecksOwnedReferences;
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.asset_categories.create',
            'accounting.asset_categories.update',
            'accounting.assets.create',
            'accounting.assets.update',
            'accounting.assets.dispose',
            'accounting.depreciation_runs.create',
            'accounting.leases.create',
        ]);
    }

    public function test_asset_category_and_branch(): void
    {
        $references = ['asset_category_id' => 'asset_categories', 'branch_id' => 'branches'];

        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/assets', $references);

        $asset = $this->ownRow('fixed_assets', ['status' => FixedAsset::STATUS_ACTIVE]);
        $this->assertOnlyOwnRowsAccepted('PUT', "/api/v1/assets/{$asset}", $references);
    }

    public function test_auc_settlement_target(): void
    {
        $source = $this->ownRow('fixed_assets', ['status' => FixedAsset::STATUS_ACTIVE]);

        $this->assertOnlyOwnRowsAccepted('POST', "/api/v1/assets/{$source}/settle-auc", ['target_asset_id' => 'fixed_assets']);
    }

    public function test_asset_category_accounts(): void
    {
        $references = [
            'gl_asset_account_id' => 'chart_of_accounts',
            'gl_depreciation_account_id' => 'chart_of_accounts',
            'gl_accumulated_account_id' => 'chart_of_accounts',
        ];

        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/asset-categories', $references);

        $category = $this->ownRow('asset_categories');
        $this->assertOnlyOwnRowsAccepted('PUT', "/api/v1/asset-categories/{$category}", $references);

        $assetAccount = $this->ownRow('chart_of_accounts');

        $this->withToken($this->token)
            ->postJson('/api/v1/asset-categories', [
                'name' => 'IT Equipment',
                'code' => 'ITE',
                'default_useful_life_years' => 3,
                'default_depreciation_method' => 'straight_line',
                'gl_asset_account_id' => $assetAccount,
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('asset_categories', [
            'organization_id' => $this->organization->id,
            'code' => 'ITE',
            'gl_asset_account_id' => $assetAccount,
        ]);
    }

    public function test_depreciation_run_fiscal_year(): void
    {
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/depreciation-runs', ['fiscal_year_id' => 'fiscal_years']);
    }

    public function test_lease_accounts(): void
    {
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/leases', [
            'rou_asset_account_id' => 'chart_of_accounts',
            'accum_depreciation_account_id' => 'chart_of_accounts',
            'lease_liability_account_id' => 'chart_of_accounts',
            'interest_expense_account_id' => 'chart_of_accounts',
            'depreciation_expense_account_id' => 'chart_of_accounts',
        ]);
    }
}
