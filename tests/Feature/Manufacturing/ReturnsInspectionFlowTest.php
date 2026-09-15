<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\ReturnsInspectionDefect;
use App\Models\Manufacturing\ReturnsInspectionLot;
use App\Services\Manufacturing\ReturnsInspectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * A returns inspection lot puts its accepted quantity back into stock once, on
 * the locked lot, reaches defects only through the lot in the URL and accepts
 * only the organization's own rows.
 */
class ReturnsInspectionFlowTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private Product $product;
    private Warehouse $store;
    private ReturnsInspectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.quality.view',
            'manufacturing.quality.manage',
        ]);

        $this->product = $this->stockedProduct();
        $this->store = $this->warehouse();
        $this->service = app(ReturnsInspectionService::class);
    }

    public function test_posting_stock_moves_the_accepted_quantity_into_the_warehouse_once(): void
    {
        $lot = $this->decidedLot(accepted: 7, rejected: 2, rework: 1);

        $this->apiPost("/manufacturing/returns-inspection/{$lot->id}/post-stock")
            ->assertOk()
            ->assertJsonPath('data.status', ReturnsInspectionLot::STATUS_CLOSED)
            ->assertJsonPath('data.stock_posted', true);

        $this->apiPost("/manufacturing/returns-inspection/{$lot->id}/post-stock")->assertStatus(422);

        $this->assertEqualsWithDelta(7.0, $this->quantityOf($this->product, $this->store), 0.0001);

        $movements = StockMovement::where('reference_type', 'returns_inspection_lot')
            ->where('reference_id', $lot->id)
            ->get();

        $this->assertCount(1, $movements);
        $this->assertSame(StockMovement::DIRECTION_IN, $movements->first()->direction);
        $this->assertSame(StockMovement::TYPE_RETURN_IN, $movements->first()->movement_type);
    }

    public function test_a_stale_copy_cannot_post_stock_twice(): void
    {
        $lot = $this->decidedLot(accepted: 5, rejected: 0, rework: 0);
        $stale = ReturnsInspectionLot::findOrFail($lot->id);

        $this->service->postStockMovements(ReturnsInspectionLot::findOrFail($lot->id));

        try {
            $this->service->postStockMovements($stale);
            $this->fail('A lot whose stock was posted posted it again.');
        } catch (InvalidArgumentException) {
            // Refused on the locked lot, as expected.
        }

        $this->assertEqualsWithDelta(5.0, $this->quantityOf($this->product, $this->store), 0.0001);
    }

    public function test_a_lot_without_a_warehouse_does_not_post_stock(): void
    {
        $lot = $this->decidedLot(accepted: 5, rejected: 0, rework: 0, warehouseId: null);

        $this->apiPost("/manufacturing/returns-inspection/{$lot->id}/post-stock")->assertStatus(422);

        $this->assertFalse($lot->fresh()->stock_posted);
        $this->assertSame(ReturnsInspectionLot::STATUS_USAGE_DECISION_MADE, $lot->fresh()->status);
    }

    public function test_a_stale_copy_cannot_change_a_decided_lot(): void
    {
        $lot = $this->lot(['status' => ReturnsInspectionLot::STATUS_IN_INSPECTION]);
        $stale = ReturnsInspectionLot::findOrFail($lot->id);

        $this->service->makeUsageDecision(ReturnsInspectionLot::findOrFail($lot->id), $this->decision(6, 4, 0));

        try {
            $this->service->makeUsageDecision($stale, $this->decision(10, 0, 0));
            $this->fail('A decided lot took a second usage decision.');
        } catch (InvalidArgumentException) {
            // Refused on the locked lot, as expected.
        }

        $this->assertEqualsWithDelta(6.0, (float) $lot->fresh()->accepted_quantity, 0.0001);
    }

    public function test_a_defect_is_reached_only_through_its_own_live_lot(): void
    {
        $lot = $this->lot();
        $other = $this->lot();
        $defect = $this->defectOn($lot);

        $this->apiPut("/manufacturing/returns-inspection/{$other->id}/defects/{$defect->id}", ['notes' => 'x'])
            ->assertNotFound();

        $lot->delete();

        $this->apiPut("/manufacturing/returns-inspection/{$lot->id}/defects/{$defect->id}", ['notes' => 'x'])
            ->assertNotFound();
        $this->apiDelete("/manufacturing/returns-inspection/{$lot->id}/defects/{$defect->id}")
            ->assertNotFound();

        $this->assertNull($defect->fresh()->notes);
    }

    public function test_a_defect_is_updated_and_removed_through_its_lot(): void
    {
        $lot = $this->lot();
        $defect = $this->defectOn($lot);

        $this->apiPut("/manufacturing/returns-inspection/{$lot->uuid}/defects/{$defect->uuid}", ['notes' => 'Checked'])
            ->assertOk()
            ->assertJsonPath('data.notes', 'Checked');

        $this->apiDelete("/manufacturing/returns-inspection/{$lot->id}/defects/{$defect->id}")->assertOk();

        $this->assertDatabaseMissing('returns_inspection_defects', ['id' => $defect->id]);
    }

    public function test_another_organizations_lot_and_defect_are_not_found(): void
    {
        $theirs = $this->lot([
            'organization_id' => $this->otherOrganization()->id,
            'product_id' => $this->foreignProduct()->id,
            'warehouse_id' => null,
        ]);
        $defect = $this->defectOn($theirs);

        $this->apiGet("/manufacturing/returns-inspection/{$theirs->id}")->assertNotFound();
        $this->apiPut("/manufacturing/returns-inspection/{$theirs->id}/defects/{$defect->id}", ['notes' => 'x'])
            ->assertNotFound();
        $this->apiDelete("/manufacturing/returns-inspection/{$theirs->id}/defects/{$defect->id}")
            ->assertNotFound();

        $this->assertNull(ReturnsInspectionDefect::withoutGlobalScopes()->find($defect->id)->notes);
    }

    public function test_another_organizations_references_are_refused_on_a_lot(): void
    {
        $this->apiPost('/manufacturing/returns-inspection', [
            'product_id' => $this->foreignProduct()->id,
            'warehouse_id' => $this->foreignWarehouse()->id,
            'received_quantity' => 5,
        ])->assertStatus(422)->assertJsonValidationErrors(['product_id', 'warehouse_id']);
    }

    public function test_listing_lots_counts_defects_without_a_query_per_lot(): void
    {
        $first = $this->lot();
        $this->defectOn($first);
        $this->defectOn($first);

        // The first request also loads the caller's permissions; measure after it.
        $this->apiGet('/manufacturing/returns-inspection')->assertOk();

        $queries = $this->queriesFor(fn () => $this->apiGet('/manufacturing/returns-inspection')
            ->assertOk()
            ->assertJsonPath('data.0.total_defects', 2));

        $this->lot();
        $this->lot();

        $this->assertSame($queries, $this->queriesFor(fn () => $this->apiGet('/manufacturing/returns-inspection')->assertOk()));
    }

    private function queriesFor(callable $request): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $request();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private function lot(array $overrides = []): ReturnsInspectionLot
    {
        return ReturnsInspectionLot::create(array_merge([
            'organization_id' => $this->organization->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->store->id,
            'lot_number' => 'RIL-'.fake()->unique()->numerify('#####'),
            'return_type' => ReturnsInspectionLot::TYPE_CUSTOMER,
            'status' => ReturnsInspectionLot::STATUS_OPEN,
            'received_quantity' => 10,
        ], $overrides));
    }

    private function decidedLot(float $accepted, float $rejected, float $rework, ?int $warehouseId = -1): ReturnsInspectionLot
    {
        return $this->lot([
            'warehouse_id' => $warehouseId === -1 ? $this->store->id : $warehouseId,
            'status' => ReturnsInspectionLot::STATUS_USAGE_DECISION_MADE,
            'usage_decision' => ReturnsInspectionLot::DECISION_PARTIAL_ACCEPT,
            'accepted_quantity' => $accepted,
            'rejected_quantity' => $rejected,
            'rework_quantity' => $rework,
        ]);
    }

    private function decision(float $accepted, float $rejected, float $rework): array
    {
        return [
            'usage_decision' => ReturnsInspectionLot::DECISION_PARTIAL_ACCEPT,
            'accepted_quantity' => $accepted,
            'rejected_quantity' => $rejected,
            'rework_quantity' => $rework,
            'user_id' => $this->user->id,
        ];
    }

    private function defectOn(ReturnsInspectionLot $lot): ReturnsInspectionDefect
    {
        return ReturnsInspectionDefect::create([
            'organization_id' => $lot->organization_id,
            'returns_inspection_lot_id' => $lot->id,
            'defect_code' => 'SCRATCH',
            'severity' => ReturnsInspectionDefect::SEVERITY_MINOR,
        ]);
    }
}
