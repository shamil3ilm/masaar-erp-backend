<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Product;
use App\Models\Inventory\StockAdjustment;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Pins the stock adjustment list, keeps line references inside the caller's
 * organization, and posts a quick adjustment together with its creation.
 */
class StockAdjustmentEndpointsTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private Warehouse $store;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'inventory.stock-adjustments.view',
            'inventory.stock-adjustments.create',
        ]);

        $this->store = $this->warehouse();
        $this->product = $this->stockedProduct();
    }

    public function test_the_list_filters_by_status_reason_and_date_newest_first(): void
    {
        $this->travelTo(now()->subHour());
        $older = $this->adjustment(StockAdjustment::STATUS_DRAFT, StockAdjustment::REASON_DAMAGE, '2025-01-10');
        $this->travelBack();
        $newer = $this->adjustment(StockAdjustment::STATUS_DRAFT, StockAdjustment::REASON_DAMAGE, '2025-01-20');
        $this->adjustment(StockAdjustment::STATUS_DRAFT, StockAdjustment::REASON_THEFT, '2025-01-15');
        $this->adjustment(StockAdjustment::STATUS_POSTED, StockAdjustment::REASON_DAMAGE, '2025-01-15');
        $this->adjustment(StockAdjustment::STATUS_DRAFT, StockAdjustment::REASON_DAMAGE, '2025-02-15');

        $response = $this->apiGet('/inventory/stock-adjustments?status=draft&reason=damage&from_date=2025-01-01&to_date=2025-01-31');

        $response->assertOk();
        $this->assertSame([$newer->id, $older->id], array_column($response->json('data'), 'id'));
        $this->assertSame(2, $response->json('meta.total'));
    }

    public function test_a_line_may_name_a_variant_and_location_of_the_organization(): void
    {
        $response = $this->apiPost('/inventory/stock-adjustments', $this->payload([
            'variant_id' => $this->variantOf($this->product)->id,
            'location_id' => $this->locationIn($this->store)->id,
        ]));

        $response->assertCreated();
    }

    public function test_a_variant_or_location_of_another_organization_is_refused(): void
    {
        $response = $this->apiPost('/inventory/stock-adjustments', $this->payload([
            'variant_id' => $this->variantOf($this->foreignProduct())->id,
            'location_id' => $this->locationIn($this->foreignWarehouse())->id,
        ]));

        $response->assertStatus(422)->assertJsonValidationErrors(['lines.0.variant_id', 'lines.0.location_id']);
        $this->assertSame(0, StockAdjustment::count());
    }

    public function test_a_quick_adjustment_that_cannot_be_posted_is_not_kept(): void
    {
        $this->stockLevel($this->product, $this->store, 10);
        StockMovement::creating(function (): void {
            throw new \RuntimeException('The movement could not be written.');
        });

        $response = $this->apiPost('/inventory/stock-adjustments/quick-adjust', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->store->id,
            'actual_quantity' => 4,
            'reason' => StockAdjustment::REASON_DAMAGE,
        ]);

        $response->assertStatus(500);
        $this->assertSame(0, StockAdjustment::count());
        $this->assertEquals(10, $this->quantityOf($this->product, $this->store));
    }

    public function test_a_quick_adjustment_is_posted(): void
    {
        $this->stockLevel($this->product, $this->store, 10);

        $response = $this->apiPost('/inventory/stock-adjustments/quick-adjust', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->store->id,
            'actual_quantity' => 4,
            'reason' => StockAdjustment::REASON_DAMAGE,
        ]);

        $response->assertOk()->assertJsonPath('data.status', StockAdjustment::STATUS_POSTED);
        $this->assertEquals(4, $this->quantityOf($this->product, $this->store));
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function payload(array $line): array
    {
        return [
            'warehouse_id' => $this->store->id,
            'adjustment_date' => now()->toDateString(),
            'reason' => StockAdjustment::REASON_COUNT_CORRECTION,
            'lines' => [array_merge(['product_id' => $this->product->id, 'actual_quantity' => 5], $line)],
        ];
    }

    private function adjustment(string $status, string $reason, string $date): StockAdjustment
    {
        return StockAdjustment::create([
            'organization_id' => $this->organization->id,
            'warehouse_id' => $this->store->id,
            'adjustment_number' => 'ADJ-'.fake()->unique()->numerify('#####'),
            'adjustment_date' => $date,
            'reason' => $reason,
            'status' => $status,
            'created_by' => $this->user->id,
        ]);
    }
}
