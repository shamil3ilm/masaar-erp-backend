<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Product;
use App\Models\Inventory\ProductBarcode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Pins the barcode list and primary switch, and keeps barcode references and
 * label printing inside the caller's organization.
 */
class BarcodeEndpointsTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'inventory.barcodes.view',
            'inventory.barcodes.manage',
        ]);

        $this->product = $this->stockedProduct();
    }

    public function test_the_list_filters_by_product_and_primary(): void
    {
        $match = $this->barcode($this->product, true);
        $this->barcode($this->product, false);
        $this->barcode($this->stockedProduct(), true);

        $response = $this->apiGet("/inventory/barcode/barcodes?product_id={$this->product->id}&is_primary=1");

        $response->assertOk();
        $this->assertSame([$match->id], array_column($response->json('data'), 'id'));
    }

    public function test_making_a_barcode_primary_clears_the_previous_primary(): void
    {
        $previous = $this->barcode($this->product, true);
        $next = $this->barcode($this->product, false);

        $this->apiPut("/inventory/barcode/barcodes/{$next->id}", ['is_primary' => true])->assertOk();

        $this->assertFalse((bool) $previous->fresh()->is_primary);
        $this->assertTrue((bool) $next->fresh()->is_primary);
    }

    public function test_another_organizations_variant_or_batch_is_refused(): void
    {
        $response = $this->apiPost('/inventory/barcode/barcodes', [
            'product_id' => $this->product->id,
            'variant_id' => $this->variantOf($this->foreignProduct())->id,
            'batch_id' => $this->foreignBatch()->id,
            'barcode_value' => 'X-1',
            'barcode_type' => 'custom',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['variant_id', 'batch_id']);
    }

    public function test_labels_refuse_another_organizations_barcode(): void
    {
        $theirs = $this->barcode($this->foreignProduct(), true);

        $response = $this->apiPost('/inventory/barcode/barcodes/print-labels', ['barcode_ids' => [$theirs->id]]);

        $response->assertStatus(422)->assertJsonValidationErrors(['barcode_ids.0']);
    }

    private function barcode(Product $product, bool $primary): ProductBarcode
    {
        return ProductBarcode::withoutGlobalScopes()->forceCreate([
            'organization_id' => $product->organization_id,
            'product_id' => $product->id,
            'barcode_value' => 'BC-'.fake()->unique()->numerify('#####'),
            'barcode_type' => 'custom',
            'usage' => 'product',
            'is_primary' => $primary,
            'is_active' => true,
        ]);
    }
}
