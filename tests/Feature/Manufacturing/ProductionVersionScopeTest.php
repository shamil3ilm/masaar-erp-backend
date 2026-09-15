<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Manufacturing\ProductionVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Production versions are found only within the organization, and one version
 * per product is the default.
 */
class ProductionVersionScopeTest extends TestCase
{
    use BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.planning.manage',
            'manufacturing.planning.view',
        ]);
    }

    public function test_another_organizations_version_is_not_found(): void
    {
        $theirs = ProductionVersion::factory()->create([
            'organization_id' => $this->otherOrganization()->id,
            'product_id' => $this->foreignProduct()->id,
        ]);

        $this->apiGet("/manufacturing/production-versions/{$theirs->id}")->assertNotFound();
        $this->apiPut("/manufacturing/production-versions/{$theirs->id}", ['description' => 'x'])->assertNotFound();
        $this->apiPost("/manufacturing/production-versions/{$theirs->id}/set-default")->assertNotFound();
        $this->apiDelete("/manufacturing/production-versions/{$theirs->id}")->assertNotFound();

        $this->assertNotSoftDeleted('production_versions', ['id' => $theirs->id]);
    }

    public function test_setting_a_default_leaves_one_default_per_product(): void
    {
        $product = Product::factory()->create(['organization_id' => $this->organization->id]);
        $old = ProductionVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'product_id' => $product->id,
            'is_default' => true,
        ]);
        $new = ProductionVersion::factory()->create([
            'organization_id' => $this->organization->id,
            'product_id' => $product->id,
        ]);

        $this->apiPost("/manufacturing/production-versions/{$new->id}/set-default")->assertOk();

        $this->assertFalse((bool) $old->fresh()->is_default);
        $this->assertTrue((bool) $new->fresh()->is_default);
    }
}
