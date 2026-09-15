<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Category;
use App\Models\Inventory\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * A product's category and unit, and the products a bulk price update names,
 * must belong to the caller's organization; barcodes are unique within it.
 */
class ProductEndpointsTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'inventory.products.create',
            'inventory.products.edit',
        ]);
    }

    public function test_another_organizations_category_or_unit_is_refused(): void
    {
        $theirCategory = Category::withoutGlobalScopes()->forceCreate([
            'organization_id' => $this->otherOrganization()->id,
            'name' => 'Theirs',
            'slug' => 'theirs',
            'is_active' => true,
        ]);

        $response = $this->apiPost('/inventory/products', [
            'sku' => 'SKU-NEW',
            'name' => 'New product',
            'type' => 'goods',
            'category_id' => $theirCategory->id,
            'unit_id' => $this->foreignUnit()->id,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['category_id', 'unit_id']);
    }

    public function test_a_barcode_used_by_another_organization_is_accepted(): void
    {
        $theirs = $this->foreignProduct();
        Product::withoutGlobalScopes()->whereKey($theirs->id)->update(['barcode' => '6281000000001']);

        $response = $this->apiPost('/inventory/products', [
            'sku' => 'SKU-NEW',
            'name' => 'New product',
            'type' => 'goods',
            'unit_id' => $this->stockedProduct()->unit_id,
            'barcode' => '6281000000001',
        ]);

        $response->assertCreated();
    }

    public function test_a_bulk_price_update_refuses_another_organizations_product(): void
    {
        $theirs = $this->foreignProduct();

        $response = $this->apiPost('/inventory/products/bulk-update-prices', [
            'updates' => [['product_id' => $theirs->id, 'selling_price' => 1]],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['updates.0.product_id']);
        $this->assertEquals(10, (float) Product::withoutGlobalScopes()->findOrFail($theirs->id)->selling_price);
    }
}
