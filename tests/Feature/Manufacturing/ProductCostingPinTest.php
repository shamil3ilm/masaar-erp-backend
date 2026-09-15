<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Manufacturing\CostingVersion;
use App\Models\Manufacturing\ProductStandardCost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Standard costs are read through their costing version, which must belong to
 * the organization.
 */
class ProductCostingPinTest extends TestCase
{
    use BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private CostingVersion $version;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.costing.view',
            'manufacturing.costing.create',
            'manufacturing.costing.run',
        ]);

        $this->version = CostingVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_standard_costs_list_only_the_versions_rows(): void
    {
        $ours = $this->standardCost($this->version, $this->product());
        $otherVersion = CostingVersion::factory()->create(['organization_id' => $this->organization->id, 'created_by' => $this->user->id]);
        $this->standardCost($otherVersion, $this->product());

        $response = $this->apiGet("/manufacturing/standard-costs/versions/{$this->version->uuid}");

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ours->id);
    }

    public function test_a_product_cost_is_shown_only_within_its_version(): void
    {
        $product = $this->product();
        $this->standardCost($this->version, $product);
        $otherVersion = CostingVersion::factory()->create(['organization_id' => $this->organization->id, 'created_by' => $this->user->id]);

        $this->apiGet("/manufacturing/standard-costs/versions/{$this->version->uuid}/products/{$product->id}")
            ->assertOk()->assertJsonPath('data.product_id', $product->id);
        $this->apiGet("/manufacturing/standard-costs/versions/{$otherVersion->uuid}/products/{$product->id}")
            ->assertNotFound();
    }

    public function test_another_organizations_version_is_not_found(): void
    {
        $theirs = CostingVersion::factory()->create(['organization_id' => $this->otherOrganization()->id]);

        $this->apiGet("/manufacturing/costing-versions/{$theirs->uuid}")->assertNotFound();
        $this->apiGet("/manufacturing/standard-costs/versions/{$theirs->uuid}")->assertNotFound();
    }

    public function test_a_created_version_starts_as_a_draft(): void
    {
        $this->apiPost('/manufacturing/costing-versions', [
            'version_code' => 'STD-1',
            'description' => 'Standard',
            'valid_from' => now()->toDateString(),
            'costing_type' => 'standard',
        ])->assertCreated()->assertJsonPath('data.status', CostingVersion::STATUS_DRAFT);
    }

    private function product(): Product
    {
        return Product::factory()->create(['organization_id' => $this->organization->id]);
    }

    private function standardCost(CostingVersion $version, Product $product): ProductStandardCost
    {
        return ProductStandardCost::forceCreate([
            'uuid' => (string) Str::uuid(),
            'costing_version_id' => $version->id,
            'product_id' => $product->id,
            'material_cost' => 4,
            'labor_cost' => 3,
            'overhead_cost' => 1,
            'total_standard_cost' => 8,
            'cost_per_unit' => 8,
        ]);
    }
}
