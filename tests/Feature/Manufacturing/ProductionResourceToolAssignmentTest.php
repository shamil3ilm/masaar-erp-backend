<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Manufacturing\ProductionResourceTool;
use App\Models\Manufacturing\WorkOrder;
use App\Services\Manufacturing\ProductionResourceToolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Tool assignments take units on the locked tool and give them back once, and
 * link only the organization's own work orders.
 */
class ProductionResourceToolAssignmentTest extends TestCase
{
    use BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.planning.manage',
            'manufacturing.planning.view',
        ]);
    }

    public function test_an_assignment_released_twice_returns_its_quantity_once(): void
    {
        $tool = $this->tool(quantity: 2);
        $first = $this->apiPost("/manufacturing/production-resources/{$tool->id}/assign", ['quantity_required' => 1])
            ->assertCreated()->json('data.id');
        $this->apiPost("/manufacturing/production-resources/{$tool->id}/assign", ['quantity_required' => 1])
            ->assertCreated();

        $this->apiPost("/manufacturing/production-resources/{$tool->id}/assignments/{$first}/release")->assertOk();
        $this->apiPost("/manufacturing/production-resources/{$tool->id}/assignments/{$first}/release")->assertStatus(422);

        $this->assertSame(1, (int) $tool->fresh()->quantity_in_use);
    }

    public function test_another_organizations_work_order_cannot_be_assigned_a_tool(): void
    {
        $tool = $this->tool(quantity: 1);
        $theirWorkOrder = WorkOrder::factory()->create(['organization_id' => $this->otherOrganization()->id]);

        $this->apiPost("/manufacturing/production-resources/{$tool->id}/assign", ['work_order_id' => $theirWorkOrder->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['work_order_id']);

        $this->assertSame(0, (int) $tool->fresh()->quantity_in_use);
    }

    public function test_a_stale_copy_cannot_assign_a_tool_beyond_its_quantity(): void
    {
        $tool = $this->tool(quantity: 1);
        $stale = ProductionResourceTool::findOrFail($tool->id);
        $service = app(ProductionResourceToolService::class);

        $service->assign(ProductionResourceTool::findOrFail($tool->id), ['quantity_required' => 1]);

        try {
            $service->assign($stale, ['quantity_required' => 1]);
            $this->fail('The tool was assigned beyond its available quantity.');
        } catch (ValidationException) {
            // Refused on the locked tool, as expected.
        }

        $this->assertSame(1, (int) $tool->fresh()->quantity_in_use);
    }

    public function test_another_organizations_tool_is_not_found(): void
    {
        $theirs = ProductionResourceTool::factory()->create(['organization_id' => $this->otherOrganization()->id]);

        $this->apiGet("/manufacturing/production-resources/{$theirs->id}")->assertNotFound();
        $this->apiPost("/manufacturing/production-resources/{$theirs->id}/assign", [])->assertNotFound();
        $this->apiDelete("/manufacturing/production-resources/{$theirs->id}")->assertNotFound();
    }

    private function tool(int $quantity): ProductionResourceTool
    {
        return ProductionResourceTool::factory()->create([
            'organization_id' => $this->organization->id,
            'quantity_available' => $quantity,
            'quantity_in_use' => 0,
        ]);
    }
}
