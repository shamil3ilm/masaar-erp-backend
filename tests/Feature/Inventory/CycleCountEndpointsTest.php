<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Inventory\CycleCountLine;
use App\Models\Inventory\CycleCountPlan;
use App\Models\Inventory\CycleCountSession;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Pins the cycle count plan list, the count and post flow, and keeps plans
 * and sessions inside the caller's organization.
 */
class CycleCountEndpointsTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private Warehouse $store;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'inventory.cycle-counts.view',
            'inventory.cycle-counts.manage',
        ]);

        $this->store = $this->warehouse();
        $this->product = $this->stockedProduct();
        $this->stockLevel($this->product, $this->store, 10);
    }

    public function test_the_plan_list_holds_the_organizations_plans_with_their_warehouse(): void
    {
        $ours = $this->plan($this->organization->id, $this->store->id);
        $this->plan($this->otherOrganization()->id, $this->foreignWarehouse()->id);

        $response = $this->apiGet('/inventory/cycle-counts/plans');

        $response->assertOk();
        $this->assertSame([$ours->id], array_column($response->json('data'), 'id'));
        $this->assertSame($this->store->id, $response->json('data.0.warehouse.id'));
    }

    public function test_a_plan_is_created_for_a_warehouse_of_the_organization(): void
    {
        $response = $this->apiPost('/inventory/cycle-counts/plans', [
            'plan_name' => 'Weekly A items',
            'warehouse_id' => $this->store->id,
            'count_frequency' => 'A',
        ]);

        $response->assertCreated()->assertJsonPath('data.organization_id', $this->organization->id);
    }

    public function test_a_plan_for_another_organizations_warehouse_is_refused(): void
    {
        $response = $this->apiPost('/inventory/cycle-counts/plans', [
            'plan_name' => 'Weekly A items',
            'warehouse_id' => $this->foreignWarehouse()->id,
            'count_frequency' => 'A',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['warehouse_id']);
        $this->assertSame(0, CycleCountPlan::withoutGlobalScopes()->count());
    }

    public function test_a_session_for_another_organizations_plan_is_refused(): void
    {
        $theirs = $this->plan($this->otherOrganization()->id, $this->foreignWarehouse()->id);

        $response = $this->apiPost('/inventory/cycle-counts/sessions', [
            'plan_id' => $theirs->id,
            'session_date' => now()->toDateString(),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['plan_id']);
        $this->assertSame(0, CycleCountSession::withoutGlobalScopes()->count());
    }

    public function test_a_count_is_recorded_and_the_session_posted_with_its_variances(): void
    {
        $plan = $this->plan($this->organization->id, $this->store->id);

        $created = $this->apiPost('/inventory/cycle-counts/sessions', [
            'plan_id' => $plan->id,
            'session_date' => now()->toDateString(),
        ])->assertCreated();

        $sessionId = $created->json('data.id');
        $lineId = $created->json('data.lines.0.id');

        $this->apiGet("/inventory/cycle-counts/sessions/{$sessionId}")
            ->assertOk()
            ->assertJsonPath('data.lines.0.product.id', $this->product->id);

        $this->apiPut("/inventory/cycle-counts/sessions/{$sessionId}/lines/{$lineId}", ['counted_quantity' => 8])
            ->assertOk()
            ->assertJsonPath('data.status', 'counted');

        $posted = $this->apiPost("/inventory/cycle-counts/sessions/{$sessionId}/post")->assertOk();

        $this->assertSame(1, $posted->json('data.total_lines'));
        $this->assertEquals(-2, $posted->json('data.variances.0.variance'));
        $this->assertSame('posted', CycleCountSession::findOrFail($sessionId)->status);
    }

    public function test_a_posted_session_is_not_posted_again_or_counted_further(): void
    {
        $plan = $this->plan($this->organization->id, $this->store->id);
        $created = $this->apiPost('/inventory/cycle-counts/sessions', ['plan_id' => $plan->id, 'session_date' => now()->toDateString()]);
        $sessionId = $created->json('data.id');
        $lineId = $created->json('data.lines.0.id');

        $this->apiPost("/inventory/cycle-counts/sessions/{$sessionId}/post")->assertOk();
        $completedAt = CycleCountSession::findOrFail($sessionId)->completed_at;

        $this->travel(5)->minutes();

        $this->apiPost("/inventory/cycle-counts/sessions/{$sessionId}/post")->assertStatus(422);
        $this->apiPut("/inventory/cycle-counts/sessions/{$sessionId}/lines/{$lineId}", ['counted_quantity' => 3])
            ->assertStatus(422);

        $this->assertEquals($completedAt, CycleCountSession::findOrFail($sessionId)->completed_at);
        $this->assertNull(CycleCountLine::findOrFail($lineId)->counted_quantity);
    }

    public function test_a_line_named_under_another_session_is_not_found(): void
    {
        $plan = $this->plan($this->organization->id, $this->store->id);
        $first = $this->apiPost('/inventory/cycle-counts/sessions', ['plan_id' => $plan->id, 'session_date' => now()->toDateString()]);
        $second = $this->apiPost('/inventory/cycle-counts/sessions', ['plan_id' => $plan->id, 'session_date' => now()->toDateString()]);

        $response = $this->apiPut(
            "/inventory/cycle-counts/sessions/{$first->json('data.id')}/lines/{$second->json('data.lines.0.id')}",
            ['counted_quantity' => 1]
        );

        $response->assertNotFound();
        $this->assertNull(CycleCountLine::findOrFail($second->json('data.lines.0.id'))->counted_quantity);
    }

    private function plan(int $organizationId, int $warehouseId): CycleCountPlan
    {
        return CycleCountPlan::forceCreate([
            'organization_id' => $organizationId,
            'plan_name' => 'Plan',
            'warehouse_id' => $warehouseId,
            'count_frequency' => 'A',
            'status' => 'active',
        ]);
    }
}
