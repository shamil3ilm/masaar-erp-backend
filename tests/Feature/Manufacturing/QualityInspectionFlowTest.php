<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\InspectionLot;
use App\Models\Manufacturing\UsageDecision;
use App\Services\Manufacturing\QualityManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Inspection lots are completed and decided once, on the locked lot, and
 * accept only the organization's own rows.
 */
class QualityInspectionFlowTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private Product $product;
    private Warehouse $store;
    private QualityManagementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.quality.view',
            'manufacturing.quality.create',
            'manufacturing.quality.edit',
            'manufacturing.quality.delete',
            'manufacturing.quality.inspect',
            'manufacturing.quality.resolve',
        ]);

        $this->product = $this->stockedProduct();
        $this->store = $this->warehouse();
        $this->service = app(QualityManagementService::class);
    }

    public function test_a_stale_copy_cannot_complete_a_lot_twice(): void
    {
        $lot = $this->lot();
        $stale = InspectionLot::findOrFail($lot->id);

        $this->service->completeInspection(InspectionLot::findOrFail($lot->id), 4, 0, $this->user->id);

        try {
            $this->service->completeInspection($stale, 2, 1, $this->user->id);
            $this->fail('A completed inspection lot was completed again.');
        } catch (\InvalidArgumentException) {
            // Refused on the locked lot, as expected.
        }

        $this->assertEqualsWithDelta(4.0, (float) $lot->fresh()->accepted_quantity, 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) $lot->fresh()->rejected_quantity, 0.0001);
    }

    public function test_a_stale_copy_cannot_record_a_second_usage_decision(): void
    {
        $lot = $this->lot();
        $stale = InspectionLot::findOrFail($lot->id);

        $this->service->recordUsageDecision(InspectionLot::findOrFail($lot->id), ['qty_unrestricted' => 5], $this->user->id);

        try {
            $this->service->recordUsageDecision($stale, ['qty_unrestricted' => 5], $this->user->id);
            $this->fail('A second usage decision was recorded on a decided lot.');
        } catch (\InvalidArgumentException) {
            // Refused on the locked lot, as expected.
        }

        $this->assertSame(1, UsageDecision::withoutGlobalScopes()->where('inspection_lot_id', $lot->id)->count());
    }

    public function test_another_organizations_product_is_refused_on_a_lot(): void
    {
        $this->apiPost('/manufacturing/quality/inspection-lots', [
            'product_id' => $this->foreignProduct()->id,
            'quantity' => 5,
        ])->assertStatus(422)->assertJsonValidationErrors(['product_id']);
    }

    public function test_another_organizations_lot_is_not_found(): void
    {
        $theirs = InspectionLot::factory()->create([
            'organization_id' => $this->otherOrganization()->id,
            'product_id' => $this->foreignProduct()->id,
            'quantity' => 5,
        ]);

        $this->apiGet("/manufacturing/quality/inspection-lots/{$theirs->id}")->assertNotFound();
        $this->apiPost("/manufacturing/quality/inspection-lots/{$theirs->id}/complete", [
            'accepted_quantity' => 5,
            'rejected_quantity' => 0,
        ])->assertNotFound();

        $this->assertSame(InspectionLot::STATUS_PENDING, $theirs->fresh()->status);
    }

    private function lot(): InspectionLot
    {
        return InspectionLot::factory()->create([
            'organization_id' => $this->organization->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->store->id,
            'quantity' => 5,
            'status' => InspectionLot::STATUS_PENDING,
        ]);
    }
}
