<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Product;
use App\Models\Inventory\SplitValuation;
use App\Models\Inventory\ValuationCategory;
use App\Models\Inventory\ValuationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * A split valuation posting may only name products, valuation types and
 * warehouses of the caller's organization.
 */
class SplitValuationEndpointsTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['inventory.split-valuation.manage']);

        $this->product = $this->stockedProduct();
    }

    public function test_a_receipt_for_the_organizations_valuation_type_is_posted(): void
    {
        $response = $this->apiPost('/inventory/split-valuation/goods-receipt', $this->receipt(
            $this->valuationType($this->organization->id, $this->product->id)->id
        ));

        $response->assertCreated();
    }

    public function test_a_receipt_for_another_organizations_valuation_type_is_refused(): void
    {
        $theirs = $this->valuationType($this->otherOrganization()->id, $this->foreignProduct()->id);

        $response = $this->apiPost('/inventory/split-valuation/goods-receipt', [
            ...$this->receipt($theirs->id),
            'warehouse_id' => $this->foreignWarehouse()->id,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['valuation_type_id', 'warehouse_id']);
        $this->assertSame(0, SplitValuation::withoutGlobalScopes()->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function receipt(int $valuationTypeId): array
    {
        return [
            'product_id' => $this->product->id,
            'valuation_type_id' => $valuationTypeId,
            'quantity' => 2,
            'unit_price' => 5,
            'currency' => 'SAR',
        ];
    }

    private function valuationType(int $organizationId, int $productId): ValuationType
    {
        $category = ValuationCategory::withoutGlobalScopes()->forceCreate([
            'organization_id' => $organizationId,
            'product_id' => $productId,
            'category_code' => 'ORIG',
            'category_name' => 'Origin',
        ]);

        return ValuationType::withoutGlobalScopes()->forceCreate([
            'organization_id' => $organizationId,
            'valuation_category_id' => $category->id,
            'type_code' => 'DOM',
            'type_name' => 'Domestic',
        ]);
    }
}
