<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Core\Branch;
use App\Models\Inventory\Product;
use App\Models\Inventory\ShelfLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Pins the shelf label list and reprint marking, and keeps label references
 * inside the caller's organization.
 */
class ShelfLabelEndpointsTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'inventory.shelf-labels.view',
            'inventory.shelf-labels.manage',
        ]);

        $this->product = $this->stockedProduct();
    }

    public function test_the_list_filters_labels_needing_reprint(): void
    {
        $match = $this->label($this->organization->id, $this->branch->id, $this->product->id, true);
        $this->label($this->organization->id, $this->branch->id, $this->product->id, false);

        $response = $this->apiGet('/inventory/barcode/shelf-labels?needs_reprint=1');

        $response->assertOk();
        $this->assertSame([$match->id], array_column($response->json('data'), 'id'));
    }

    public function test_a_price_change_marks_the_label_for_reprint(): void
    {
        $label = $this->label($this->organization->id, $this->branch->id, $this->product->id, false);

        $this->apiPut("/inventory/barcode/shelf-labels/{$label->id}", ['price' => 12.5])->assertOk();

        $this->assertTrue((bool) $label->fresh()->needs_reprint);
    }

    public function test_another_organizations_product_or_branch_is_refused(): void
    {
        $theirBranch = Branch::factory()->create(['organization_id' => $this->otherOrganization()->id]);

        $response = $this->apiPost('/inventory/barcode/shelf-labels', [
            'branch_id' => $theirBranch->id,
            'product_id' => $this->foreignProduct()->id,
            'currency_code' => 'SAR',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['branch_id', 'product_id']);
    }

    public function test_reprint_refuses_another_organizations_label(): void
    {
        $theirProduct = $this->foreignProduct();
        $theirBranch = Branch::factory()->create(['organization_id' => $this->otherOrganization()->id]);
        $theirs = $this->label($this->otherOrganization()->id, $theirBranch->id, $theirProduct->id, false);

        $response = $this->apiPost('/inventory/barcode/shelf-labels/reprint', ['label_ids' => [$theirs->id]]);

        $response->assertStatus(422)->assertJsonValidationErrors(['label_ids.0']);
        $this->assertFalse((bool) ShelfLabel::withoutGlobalScopes()->findOrFail($theirs->id)->needs_reprint);
    }

    private function label(int $organizationId, int $branchId, int $productId, bool $needsReprint): ShelfLabel
    {
        return ShelfLabel::withoutGlobalScopes()->forceCreate([
            'organization_id' => $organizationId,
            'branch_id' => $branchId,
            'product_id' => $productId,
            'product_name' => 'Product',
            'sku' => 'SKU',
            'price' => 10,
            'currency_code' => 'SAR',
            'needs_reprint' => $needsReprint,
            'is_digital' => false,
            'is_active' => true,
        ]);
    }
}
