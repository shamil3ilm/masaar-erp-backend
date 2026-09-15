<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Accounting\CostElement;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\ProductCostCollector;
use App\Models\Manufacturing\ProductCostCollectorItem;
use App\Services\Manufacturing\ProductCostCollectorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Cost collectors take cost only while open, close once, and accept only the
 * organization's own cost elements.
 */
class ProductCostCollectorPostingTest extends TestCase
{
    use BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private ProductCostCollectorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.production.manage',
            'manufacturing.production.view',
        ]);

        $this->service = app(ProductCostCollectorService::class);
    }

    public function test_another_organizations_cost_element_is_refused(): void
    {
        $collector = $this->collector();
        $theirElement = CostElement::withoutGlobalScopes()->forceCreate([
            'organization_id' => $this->otherOrganization()->id,
            'code' => 'CE-THEIRS',
            'name' => 'Their element',
            'element_type' => 'primary',
        ]);

        $response = $this->apiPost("/manufacturing/cost-collectors/{$collector->id}/post-cost", [
            'cost_element_id' => $theirElement->id,
            'cost_category' => 'material',
            'standard_cost' => 10,
            'actual_cost' => 12,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['cost_element_id']);
        $this->assertSame(0, ProductCostCollectorItem::withoutGlobalScopes()->count());
    }

    public function test_a_stale_close_is_refused(): void
    {
        $collector = $this->collector();
        $stale = ProductCostCollector::findOrFail($collector->id);

        $this->service->close(ProductCostCollector::findOrFail($collector->id));

        $this->expectException(\InvalidArgumentException::class);
        $this->service->close($stale);
    }

    public function test_cost_is_not_posted_to_a_collector_closed_meanwhile(): void
    {
        $collector = $this->collector();
        $stale = ProductCostCollector::findOrFail($collector->id);

        $this->service->close(ProductCostCollector::findOrFail($collector->id));

        try {
            $this->service->postCost($stale, ['cost_category' => 'labor', 'standard_cost' => 5, 'actual_cost' => 6]);
            $this->fail('Cost was posted to a closed collector.');
        } catch (\InvalidArgumentException) {
            // Refused on the locked row, as expected.
        }

        $this->assertSame(0, ProductCostCollectorItem::withoutGlobalScopes()->count());
    }

    public function test_another_organizations_collector_is_not_found(): void
    {
        $theirs = $this->service->getOrCreate(
            $this->foreignProduct()->id,
            null,
            1,
            2026,
            $this->otherOrganization()->id,
        );

        $this->apiGet("/manufacturing/cost-collectors/{$theirs->id}")->assertNotFound();
        $this->apiPost("/manufacturing/cost-collectors/{$theirs->id}/close")->assertNotFound();

        $this->assertTrue($theirs->fresh()->isOpen());
    }

    private function collector(): ProductCostCollector
    {
        $product = Product::factory()->create(['organization_id' => $this->organization->id]);

        return $this->service->getOrCreate($product->id, null, 1, 2026, $this->organization->id);
    }
}
