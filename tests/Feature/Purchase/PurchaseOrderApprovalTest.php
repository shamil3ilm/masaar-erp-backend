<?php

declare(strict_types=1);

namespace Tests\Feature\Purchase;

use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\ReleaseStrategy;
use App\Models\Purchase\ReleaseStrategyApproval;
use App\Models\Purchase\ReleaseStrategyLevel;
use App\Models\Sales\Contact;
use App\Services\Purchase\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * A purchase order above the approval threshold, in an organization without
 * an approval workflow, waits in pending_approval until it is approved
 * (confirmed) or rejected (cancelled). Approval and rejection act on the
 * locked order.
 */
class PurchaseOrderApprovalTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private const THRESHOLD = 1000;

    private string $baseUrl = '/purchase/purchase-orders';
    private Contact $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        config(['erp.po_approval_threshold' => self::THRESHOLD]);

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'purchase.orders.view', 'purchase.orders.create', 'purchase.orders.send',
            'purchase.orders.receive', 'purchase.orders.approve', 'purchase.release-approvals.manage',
        ]);

        $this->supplier = Contact::factory()->supplier()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_an_order_above_the_threshold_is_created_pending_approval_without_a_workflow(): void
    {
        $response = $this->createOrder(unitPrice: 5000)->assertCreated();

        $this->assertSame(PurchaseOrder::STATUS_PENDING_APPROVAL, $response->json('data.status'));
        $this->assertSame(
            PurchaseOrder::STATUS_PENDING_APPROVAL,
            PurchaseOrder::findOrFail($response->json('data.id'))->status
        );
    }

    public function test_an_order_below_the_threshold_is_created_as_a_draft_and_can_be_sent(): void
    {
        $id = $this->createOrder(unitPrice: 100)
            ->assertCreated()
            ->assertJsonPath('data.status', PurchaseOrder::STATUS_DRAFT)
            ->json('data.id');

        $this->apiPost("{$this->baseUrl}/{$id}/send")
            ->assertOk()
            ->assertJsonPath('data.status', PurchaseOrder::STATUS_SENT);
    }

    public function test_an_order_pending_approval_cannot_be_sent_or_received(): void
    {
        $order = $this->pendingOrder();
        $lineId = $order->lines()->value('id');

        $this->apiPost("{$this->baseUrl}/{$order->id}/send")->assertStatus(422);
        $this->apiPost("{$this->baseUrl}/{$order->id}/receive", ['line_quantities' => [$lineId => 1]])
            ->assertStatus(422);

        $this->assertSame(PurchaseOrder::STATUS_PENDING_APPROVAL, $order->fresh()->status);
        $this->assertEqualsWithDelta(0.0, (float) $order->lines()->value('quantity_received'), 0.0001);
    }

    public function test_approving_confirms_the_order_which_can_then_be_received(): void
    {
        $order = $this->pendingOrder();
        $lineId = $order->lines()->value('id');

        $this->review($order, ['action' => 'approve', 'notes' => 'Within budget'])
            ->assertOk()
            ->assertJsonPath('data.status', PurchaseOrder::STATUS_CONFIRMED);

        $order->refresh();
        $this->assertSame($this->user->id, $order->approved_by);
        $this->assertNotNull($order->approved_at);
        $this->assertStringContainsString('Within budget', (string) $order->notes);

        $this->apiPost("{$this->baseUrl}/{$order->id}/receive", ['line_quantities' => [$lineId => 1]])
            ->assertOk()
            ->assertJsonPath('data.status', PurchaseOrder::STATUS_RECEIVED);
    }

    public function test_rejecting_cancels_the_order_and_it_cannot_be_approved_afterwards(): void
    {
        $order = $this->pendingOrder();

        $this->review($order, ['action' => 'reject', 'reason' => 'Over budget'])
            ->assertOk()
            ->assertJsonPath('data.status', PurchaseOrder::STATUS_CANCELLED);

        $this->assertStringContainsString('Over budget', (string) $order->fresh()->notes);

        $this->review($order, ['action' => 'approve'])->assertStatus(422);
        $this->apiPost("{$this->baseUrl}/{$order->id}/send")->assertStatus(422);
        $this->assertSame(PurchaseOrder::STATUS_CANCELLED, $order->fresh()->status);
    }

    public function test_a_stale_copy_of_an_order_rejected_meanwhile_is_not_approved(): void
    {
        $this->actingAs($this->user, 'api');
        $order = $this->pendingOrder();
        $stale = PurchaseOrder::findOrFail($order->id);
        $service = app(PurchaseOrderService::class);

        $service->rejectPO(PurchaseOrder::findOrFail($order->id), $this->user->id, 'Over budget');

        $this->assertRejected(fn () => $service->approvePO($stale, $this->user->id));
        $this->assertSame(PurchaseOrder::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertNull($order->fresh()->approved_by);
    }

    public function test_a_stale_copy_of_an_order_approved_meanwhile_is_not_rejected(): void
    {
        $this->actingAs($this->user, 'api');
        $order = $this->pendingOrder();
        $stale = PurchaseOrder::findOrFail($order->id);
        $service = app(PurchaseOrderService::class);

        $service->approvePO(PurchaseOrder::findOrFail($order->id), $this->user->id);

        $this->assertRejected(fn () => $service->rejectPO($stale, $this->user->id, 'Too late'));
        $this->assertSame(PurchaseOrder::STATUS_CONFIRMED, $order->fresh()->status);
    }

    public function test_approving_the_last_release_level_confirms_the_order(): void
    {
        $strategy = ReleaseStrategy::create([
            'organization_id' => $this->organization->id,
            'name' => 'Purchase orders',
            'document_type' => ReleaseStrategy::DOCUMENT_TYPE_PURCHASE_ORDER,
            'is_active' => true,
        ]);
        ReleaseStrategyLevel::create([
            'organization_id' => $this->organization->id,
            'release_strategy_id' => $strategy->id,
            'level' => 1,
            'role' => 'manager',
            'label' => 'Manager',
        ]);

        $id = $this->createOrder(unitPrice: 5000)
            ->assertCreated()
            ->assertJsonPath('data.status', PurchaseOrder::STATUS_PENDING_APPROVAL)
            ->json('data.id');

        $approval = ReleaseStrategyApproval::where('document_type', ReleaseStrategy::DOCUMENT_TYPE_PURCHASE_ORDER)
            ->where('document_id', $id)
            ->firstOrFail();

        $this->apiPost("/purchase/release-approvals/{$approval->id}/approve", ['comments' => 'Fine'])->assertOk();

        $order = PurchaseOrder::findOrFail($id);
        $this->assertSame(PurchaseOrder::STATUS_CONFIRMED, $order->status);
        $this->assertSame($this->user->id, $order->approved_by);
    }

    private function createOrder(float $unitPrice): TestResponse
    {
        return $this->apiPost($this->baseUrl, [
            'supplier_id' => $this->supplier->id,
            'order_date' => now()->toDateString(),
            'lines' => [['description' => 'Steel sheet', 'quantity' => 1, 'unit_price' => $unitPrice]],
        ]);
    }

    private function pendingOrder(): PurchaseOrder
    {
        return PurchaseOrder::findOrFail($this->createOrder(unitPrice: 5000)->assertCreated()->json('data.id'));
    }

    private function review(PurchaseOrder $order, array $data): TestResponse
    {
        return $this->apiPost("{$this->baseUrl}/{$order->id}/review-approval", $data);
    }

    private function assertRejected(callable $action): void
    {
        try {
            $action();
        } catch (\InvalidArgumentException) {
            return;
        }

        $this->fail('The change was expected to be rejected.');
    }
}
