<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Manufacturing\ProcessOrder;
use App\Models\Manufacturing\ProcessOrderPhase;
use App\Models\Manufacturing\Recipe;
use App\Services\Manufacturing\ProcessOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Process orders and their phases change state once, on the locked row, and
 * only within the organization. Phases have no organization column and are
 * reached through their order.
 */
class ProcessOrderExecutionTest extends TestCase
{
    use BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private Product $product;
    private ProcessOrderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.production.manage',
            'manufacturing.production.view',
        ]);

        $this->product = Product::factory()->create(['organization_id' => $this->organization->id]);
        $this->service = app(ProcessOrderService::class);
    }

    public function test_another_organizations_phase_cannot_be_started_or_completed(): void
    {
        $theirs = $this->order($this->otherOrganization()->id, $this->foreignProduct()->id, ProcessOrder::STATUS_RELEASED);
        $phase = $this->phaseOf($theirs);

        $this->apiPost("/manufacturing/process/phases/{$phase->id}/start")->assertNotFound();
        $this->apiPost("/manufacturing/process/phases/{$phase->id}/complete")->assertNotFound();

        $this->assertSame(ProcessOrderPhase::STATUS_PENDING, $phase->fresh()->status);
    }

    public function test_another_organizations_unit_is_refused_on_a_recipe(): void
    {
        $response = $this->apiPost('/manufacturing/process/recipes', [
            'product_id' => $this->product->id,
            'recipe_code' => 'RCP-1',
            'name' => 'Blend',
            'base_quantity' => 1,
            'base_unit_id' => $this->foreignUnit()->id,
            'validity_from' => now()->toDateString(),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['base_unit_id']);
    }

    public function test_a_stale_release_is_refused(): void
    {
        $order = $this->order($this->organization->id, $this->product->id, ProcessOrder::STATUS_CREATED);
        $stale = ProcessOrder::findOrFail($order->id);

        $this->service->release(ProcessOrder::findOrFail($order->id));

        $this->expectException(\LogicException::class);
        $this->service->release($stale);
    }

    public function test_a_stale_completion_is_refused(): void
    {
        $order = $this->order($this->organization->id, $this->product->id, ProcessOrder::STATUS_RELEASED);
        $stale = ProcessOrder::findOrFail($order->id);

        $this->service->complete(ProcessOrder::findOrFail($order->id), 5);

        try {
            $this->service->complete($stale, 7);
            $this->fail('A second completion of the same process order was accepted.');
        } catch (\LogicException) {
            // Refused on the locked row, as expected.
        }

        $this->assertEqualsWithDelta(5.0, (float) $order->fresh()->actual_quantity, 0.0001);
    }

    public function test_a_phase_started_twice_is_refused(): void
    {
        $order = $this->order($this->organization->id, $this->product->id, ProcessOrder::STATUS_RELEASED);
        $phase = $this->phaseOf($order);
        $stale = ProcessOrderPhase::findOrFail($phase->id);

        $this->service->startPhase(ProcessOrderPhase::findOrFail($phase->id));

        $this->expectException(\LogicException::class);
        $this->service->startPhase($stale);
    }

    private function order(int $organizationId, int $productId, string $status): ProcessOrder
    {
        $recipe = Recipe::factory()->create(['organization_id' => $organizationId, 'product_id' => $productId]);

        return ProcessOrder::withoutGlobalScopes()->forceCreate([
            'organization_id' => $organizationId,
            'recipe_id' => $recipe->id,
            'product_id' => $productId,
            'order_number' => 'PRO-'.fake()->unique()->numerify('#####'),
            'planned_quantity' => 10,
            'planned_start' => now(),
            'planned_finish' => now()->addDay(),
            'status' => $status,
        ]);
    }

    private function phaseOf(ProcessOrder $order): ProcessOrderPhase
    {
        return ProcessOrderPhase::forceCreate([
            'process_order_id' => $order->id,
            'phase_number' => 1,
            'name' => 'Mix',
            'status' => ProcessOrderPhase::STATUS_PENDING,
        ]);
    }
}
