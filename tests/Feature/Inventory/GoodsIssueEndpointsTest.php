<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Inventory\GoodsIssue;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Pins the goods issue list and keeps every line reference inside the
 * caller's organization.
 */
class GoodsIssueEndpointsTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private Warehouse $store;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'inventory.goods-issues.view',
            'inventory.goods-issues.create',
        ]);

        $this->store = $this->warehouse();
        $this->product = $this->stockedProduct();
    }

    public function test_the_list_filters_by_status_and_movement_type(): void
    {
        $match = $this->issue(GoodsIssue::STATUS_DRAFT, GoodsIssue::MOVEMENT_SCRAPPING);
        $this->issue(GoodsIssue::STATUS_POSTED, GoodsIssue::MOVEMENT_SCRAPPING);
        $this->issue(GoodsIssue::STATUS_DRAFT, GoodsIssue::MOVEMENT_OTHER);

        $response = $this->apiGet('/inventory/goods-issues?status=draft&movement_type=scrapping');

        $response->assertOk();
        $this->assertSame([$match->id], array_column($response->json('data'), 'id'));
    }

    public function test_line_references_of_the_organization_are_accepted(): void
    {
        $response = $this->apiPost('/inventory/goods-issues', $this->payload([
            'variant_id' => $this->variantOf($this->product)->id,
            'location_id' => $this->locationIn($this->store)->id,
            'batch_id' => $this->batch($this->product, $this->store, 5)->id,
            'unit_id' => $this->product->unit_id,
        ]));

        $response->assertCreated();
    }

    public function test_line_references_of_another_organization_are_refused(): void
    {
        $response = $this->apiPost('/inventory/goods-issues', $this->payload([
            'variant_id' => $this->variantOf($this->foreignProduct())->id,
            'location_id' => $this->locationIn($this->foreignWarehouse())->id,
            'batch_id' => $this->foreignBatch()->id,
            'unit_id' => $this->foreignUnit()->id,
        ]));

        $response->assertStatus(422)->assertJsonValidationErrors([
            'lines.0.variant_id',
            'lines.0.location_id',
            'lines.0.batch_id',
            'lines.0.unit_id',
        ]);
        $this->assertSame(0, GoodsIssue::count());
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function payload(array $line): array
    {
        return [
            'gi_date' => now()->toDateString(),
            'movement_type' => GoodsIssue::MOVEMENT_OTHER,
            'warehouse_id' => $this->store->id,
            'lines' => [array_merge(['product_id' => $this->product->id, 'quantity' => 1], $line)],
        ];
    }

    private function issue(string $status, string $movementType): GoodsIssue
    {
        return GoodsIssue::create([
            'organization_id' => $this->organization->id,
            'gi_number' => 'GI-'.fake()->unique()->numerify('#####'),
            'gi_date' => now()->toDateString(),
            'movement_type' => $movementType,
            'warehouse_id' => $this->store->id,
            'status' => $status,
            'total_quantity' => 0,
            'total_value' => 0,
        ]);
    }
}
