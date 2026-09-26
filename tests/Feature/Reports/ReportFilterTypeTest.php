<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\Warehouse;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\BuildsPostings;
use Tests\Traits\TestHelpers;

/**
 * The optional filters on the report endpoints.
 *
 * A query string hands every value over as text while the report services
 * take their filters as integers, so each of these once answered a filtered
 * request with a type error.
 */
class ReportFilterTypeTest extends TestCase
{
    use BuildsPostings, RefreshDatabase, TestHelpers;

    private Warehouse $warehouse;

    private Product $product;

    private Contact $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['inventory.reports.view', 'sales.reports.view']);

        $this->warehouse = Warehouse::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
        ]);
        $this->product = Product::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => Product::TYPE_GOODS,
        ]);
        $this->customer = Contact::factory()->create(['organization_id' => $this->organization->id]);
    }

    public static function filteredEndpoints(): array
    {
        return [
            'stock valuation by warehouse' => ['/reports/inventory/stock-valuation?warehouse_id=%d'],
            'low stock by warehouse' => ['/reports/inventory/low-stock?warehouse_id=%d'],
            'batch expiry by warehouse' => ['/reports/inventory/batch-expiry?days_ahead=30&warehouse_id=%d'],
            'stock movement by warehouse' => [
                '/reports/inventory/stock-movement?start_date=2026-03-01&end_date=2026-03-31&warehouse_id=%d',
            ],
        ];
    }

    #[DataProvider('filteredEndpoints')]
    public function test_an_inventory_filter_is_answered_rather_than_refused(string $uri): void
    {
        $this->apiGet(sprintf($uri, $this->warehouse->id))->assertOk();
    }

    public function test_a_sales_filter_is_answered_rather_than_refused(): void
    {
        $this->apiGet(
            "/reports/sales/by-customer?start_date=2026-03-01&end_date=2026-03-31&customer_id={$this->customer->id}&limit=10"
        )->assertOk();

        $this->apiGet(
            "/reports/sales/by-product?start_date=2026-03-01&end_date=2026-03-31&product_id={$this->product->id}&limit=10"
        )->assertOk();
    }

    public function test_a_warehouse_filter_narrows_the_stock_valuation_to_that_warehouse(): void
    {
        $elsewhere = Warehouse::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
        ]);

        $this->stock($this->warehouse, '10.0000', '5.0000');
        $this->stock($elsewhere, '100.0000', '5.0000');

        $report = $this->apiGet("/reports/inventory/stock-valuation?warehouse_id={$this->warehouse->id}")
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $report['items']);
        $this->assertSame('50.0000', $this->money($report['summary']['total_value']));
    }

    private function stock(Warehouse $warehouse, string $quantity, string $averageCost): void
    {
        StockLevel::factory()->create([
            'organization_id' => $this->organization->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $this->product->id,
            'quantity' => $quantity,
            'reserved_quantity' => '0.0000',
            'average_cost' => $averageCost,
        ]);
    }
}
