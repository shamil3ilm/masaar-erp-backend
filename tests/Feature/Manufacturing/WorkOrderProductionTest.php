<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Manufacturing\WorkOrderMaterial;
use App\Models\Manufacturing\WorkOrderOperation;
use App\Models\System\Setting;
use App\Services\Manufacturing\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsLedger;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Work order writes that move stock or post to the ledger: each runs once, on
 * the locked work order, and accepts only the organization's own rows.
 */
class WorkOrderProductionTest extends TestCase
{
    use BuildsInventory, BuildsLedger, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private Product $product;
    private Warehouse $store;
    private BomTemplate $bom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.workorders.view',
            'manufacturing.workorders.create',
            'manufacturing.workorders.edit',
            'manufacturing.workorders.start',
            'manufacturing.workorders.complete',
            'manufacturing.workorders.produce',
        ]);

        $this->product = $this->stockedProduct();
        $this->store = $this->warehouse();
        $this->bom = BomTemplate::factory()->active()->create([
            'organization_id' => $this->organization->id,
            'product_id' => $this->product->id,
        ]);
    }

    public function test_another_organizations_user_cannot_be_assigned(): void
    {
        $response = $this->apiPost('/manufacturing/work-orders', [
            'bom_template_id' => $this->bom->id,
            'planned_quantity' => 5,
            'planned_start_date' => now()->toDateString(),
            'assigned_to' => $this->foreignUser()->id,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['assigned_to']);
    }

    public function test_another_organizations_material_is_refused_on_issue(): void
    {
        $workOrder = $this->workOrderInProgress();
        $theirMaterial = WorkOrderMaterial::factory()->create([
            'work_order_id' => WorkOrder::factory()->inProgress()->create([
                'organization_id' => $this->otherOrganization()->id,
            ])->id,
        ]);

        $response = $this->apiPost("/manufacturing/work-orders/{$workOrder->uuid}/issue-materials", [
            'issues' => [['work_order_material_id' => $theirMaterial->id, 'quantity' => 1]],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['issues.0.work_order_material_id']);
    }

    public function test_material_is_not_issued_beyond_the_required_quantity_across_requests(): void
    {
        $workOrder = $this->workOrderInProgress();
        $material = $this->materialOf($workOrder, required: 10);
        $this->stockLevel($this->product, $this->store, 50);
        $issue = ['issues' => [['work_order_material_id' => $material->id, 'quantity' => 10]]];

        $this->apiPost("/manufacturing/work-orders/{$workOrder->uuid}/issue-materials", $issue)->assertOk();
        $this->apiPost("/manufacturing/work-orders/{$workOrder->uuid}/issue-materials", $issue)->assertStatus(422);

        $this->assertEqualsWithDelta(40.0, $this->quantityOf($this->product, $this->store), 0.0001);
        $this->assertEqualsWithDelta(10.0, (float) $material->fresh()->issued_quantity, 0.0001);
    }

    public function test_returning_more_than_was_issued_is_refused(): void
    {
        $workOrder = $this->workOrderInProgress();
        $material = $this->materialOf($workOrder, required: 10);

        $response = $this->apiPost("/manufacturing/work-orders/{$workOrder->uuid}/return-materials", [
            'returns' => [['work_order_material_id' => $material->id, 'quantity' => 5]],
        ]);

        $response->assertStatus(422);
        $this->assertSame(0.0, $this->quantityOf($this->product, $this->store));
    }

    public function test_a_stale_release_is_refused(): void
    {
        $workOrder = WorkOrder::factory()->draft()->create($this->ownedBy());
        $stale = WorkOrder::findOrFail($workOrder->id);
        $service = app(WorkOrderService::class);

        $service->release(WorkOrder::findOrFail($workOrder->id));

        $this->expectException(\InvalidArgumentException::class);
        $service->release($stale);
    }

    public function test_a_stale_completion_posts_its_journal_once(): void
    {
        $finishedGoods = $this->ledgerAccount('1300', 'Finished goods', Account::TYPE_ASSET, Account::SUBTYPE_INVENTORY);
        $wip = $this->ledgerAccount('1310', 'Work in process', Account::TYPE_ASSET, Account::SUBTYPE_INVENTORY);
        Setting::set('accounting', 'wip_account_id', $wip->id, null, $this->organization->id);

        $workOrder = $this->workOrderInProgress([
            'planned_quantity' => 5,
            'produced_quantity' => 5,
            'estimated_overhead_cost' => 200,
        ]);
        $stale = WorkOrder::findOrFail($workOrder->id);
        $service = app(WorkOrderService::class);

        $service->complete(WorkOrder::findOrFail($workOrder->id));

        try {
            $service->complete($stale);
            $this->fail('A second completion of the same work order was accepted.');
        } catch (\InvalidArgumentException) {
            // Refused on the locked row, as expected.
        }

        $entries = JournalEntry::where('source_type', WorkOrder::class)->where('source_id', $workOrder->id)->with('lines')->get();
        $this->assertCount(1, $entries);
        $this->assertEqualsWithDelta(200.0, (float) $entries->first()->lines->where('account_id', $finishedGoods->id)->sum('debit'), 0.0001);
        $this->assertEqualsWithDelta(200.0, (float) $entries->first()->lines->where('account_id', $wip->id)->sum('credit'), 0.0001);
    }

    public function test_completion_posts_nothing_without_a_mapped_wip_account(): void
    {
        $this->ledgerAccount('1300', 'Finished goods', Account::TYPE_ASSET, Account::SUBTYPE_INVENTORY);
        $workOrder = $this->workOrderInProgress(['produced_quantity' => 5, 'estimated_overhead_cost' => 200]);

        $this->apiPost("/manufacturing/work-orders/{$workOrder->uuid}/complete")->assertOk();

        $this->assertSame(0, JournalEntry::where('source_type', WorkOrder::class)->where('source_id', $workOrder->id)->count());
    }

    public function test_work_orders_sort_by_their_planned_start_date(): void
    {
        $last = WorkOrder::factory()->create($this->ownedBy(['planned_start_date' => now()->addDays(3)]));
        $first = WorkOrder::factory()->create($this->ownedBy(['planned_start_date' => now()->addDay()]));

        $this->apiGet('/manufacturing/work-orders?sort_by=start_date&sort_order=asc')
            ->assertOk()->assertJsonPath('data.0.id', $first->id);
        $this->apiGet('/manufacturing/work-orders?sort_by=start_date&sort_order=desc')
            ->assertOk()->assertJsonPath('data.0.id', $last->id);
    }

    public function test_an_operation_of_another_work_order_cannot_be_started(): void
    {
        $workOrder = $this->workOrderInProgress();
        $operation = WorkOrderOperation::factory()->create([
            'work_order_id' => $this->workOrderInProgress()->id,
            'status' => WorkOrderOperation::STATUS_PENDING,
        ]);

        $this->apiPost("/manufacturing/work-orders/{$workOrder->uuid}/operations/{$operation->id}/start")
            ->assertStatus(422);

        $this->assertSame(WorkOrderOperation::STATUS_PENDING, $operation->fresh()->status);
    }

    private function workOrderInProgress(array $overrides = []): WorkOrder
    {
        return WorkOrder::factory()->inProgress()->create($this->ownedBy(array_merge([
            'source_warehouse_id' => $this->store->id,
            'target_warehouse_id' => $this->store->id,
        ], $overrides)));
    }

    private function materialOf(WorkOrder $workOrder, float $required): WorkOrderMaterial
    {
        return WorkOrderMaterial::factory()->create([
            'work_order_id' => $workOrder->id,
            'product_id' => $this->product->id,
            'required_quantity' => $required,
            'unit_cost' => 5,
            'total_cost' => 0,
            'warehouse_id' => $this->store->id,
        ]);
    }

    private function ownedBy(array $attributes = []): array
    {
        return array_merge([
            'organization_id' => $this->organization->id,
            'bom_template_id' => $this->bom->id,
            'product_id' => $this->product->id,
        ], $attributes);
    }
}
