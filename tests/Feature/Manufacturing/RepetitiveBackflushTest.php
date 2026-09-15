<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\ProductionLine;
use App\Models\Manufacturing\RepetitiveMfgSchedule;
use App\Models\Manufacturing\RepetitiveMfgScheduleLine;
use App\Services\Manufacturing\RepetitiveManufacturingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Repetitive manufacturing confirmations and backflushes: schedule lines are
 * reached through their schedule, confirmations add up on the locked line, and
 * a backflush issues its components through the stock service.
 */
class RepetitiveBackflushTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private ProductionLine $line;
    private Product $product;
    private Warehouse $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.production.manage',
            'manufacturing.production.view',
        ]);

        $this->line = ProductionLine::factory()->create(['organization_id' => $this->organization->id]);
        $this->product = $this->stockedProduct();
        $this->store = $this->warehouse();
    }

    public function test_another_organizations_schedule_line_cannot_be_confirmed(): void
    {
        $theirSchedule = RepetitiveMfgSchedule::withoutGlobalScopes()->forceCreate([
            'organization_id' => $this->otherOrganization()->id,
            'product_id' => $this->foreignProduct()->id,
            'production_line_id' => ProductionLine::factory()->create(['organization_id' => $this->otherOrganization()->id])->id,
            'schedule_date_from' => now()->toDateString(),
            'schedule_date_to' => now()->toDateString(),
            'total_planned_quantity' => 10,
            'total_confirmed_quantity' => 0,
            'status' => RepetitiveMfgSchedule::STATUS_PLANNED,
        ]);
        $theirLine = RepetitiveMfgScheduleLine::forceCreate([
            'repetitive_mfg_schedule_id' => $theirSchedule->id,
            'schedule_date' => now()->toDateString(),
            'planned_quantity' => 10,
            'confirmed_quantity' => 0,
            'status' => RepetitiveMfgScheduleLine::STATUS_PLANNED,
        ]);

        $this->apiPost("/manufacturing/repetitive-manufacturing/schedule-lines/{$theirLine->id}/confirm", ['quantity' => 5])
            ->assertNotFound();

        $this->assertEqualsWithDelta(0.0, (float) $theirLine->fresh()->confirmed_quantity, 0.0001);
    }

    public function test_two_confirmations_of_a_line_both_count(): void
    {
        $schedule = $this->schedule();
        $lineId = $schedule->lines->first()->id;
        $stale = RepetitiveMfgScheduleLine::findOrFail($lineId);
        $service = app(RepetitiveManufacturingService::class);

        $service->confirmScheduleLine(RepetitiveMfgScheduleLine::findOrFail($lineId), 2);
        $service->confirmScheduleLine($stale, 2);

        $this->assertEqualsWithDelta(4.0, (float) RepetitiveMfgScheduleLine::findOrFail($lineId)->confirmed_quantity, 0.0001);
        $this->assertEqualsWithDelta(4.0, (float) $schedule->fresh()->total_confirmed_quantity, 0.0001);
    }

    public function test_a_backflush_issues_its_components_from_stock(): void
    {
        $component = $this->stockedProduct();
        $this->stockLevel($component, $this->store, 20);
        $schedule = $this->schedule();

        $response = $this->apiPost('/manufacturing/repetitive-manufacturing/backflush', [
            'repetitive_mfg_schedule_id' => $schedule->id,
            'quantity_produced' => 5,
            'component_movements' => [
                ['product_id' => $component->id, 'quantity' => 3, 'warehouse_id' => $this->store->id],
            ],
        ]);

        $response->assertCreated();
        $this->assertEqualsWithDelta(17.0, $this->quantityOf($component, $this->store), 0.0001);
    }

    public function test_a_backflush_component_of_another_organization_is_refused(): void
    {
        $schedule = $this->schedule();

        $response = $this->apiPost('/manufacturing/repetitive-manufacturing/backflush', [
            'repetitive_mfg_schedule_id' => $schedule->id,
            'quantity_produced' => 5,
            'component_movements' => [
                ['product_id' => $this->foreignProduct()->id, 'quantity' => 3, 'warehouse_id' => $this->store->id],
            ],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['component_movements.0.product_id']);
    }

    public function test_a_backflush_component_names_its_warehouse(): void
    {
        $component = $this->stockedProduct();
        $schedule = $this->schedule();

        $response = $this->apiPost('/manufacturing/repetitive-manufacturing/backflush', [
            'repetitive_mfg_schedule_id' => $schedule->id,
            'quantity_produced' => 5,
            'component_movements' => [
                ['product_id' => $component->id, 'quantity' => 3],
            ],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['component_movements.0.warehouse_id']);
    }

    private function schedule(): RepetitiveMfgSchedule
    {
        $id = $this->apiPost('/manufacturing/repetitive-manufacturing/schedules', [
            'product_id' => $this->product->id,
            'production_line_id' => $this->line->id,
            'schedule_date_from' => now()->toDateString(),
            'schedule_date_to' => now()->addDay()->toDateString(),
            'total_planned_quantity' => 10,
        ])->assertCreated()->json('data.id');

        return RepetitiveMfgSchedule::with('lines')->findOrFail($id);
    }
}
