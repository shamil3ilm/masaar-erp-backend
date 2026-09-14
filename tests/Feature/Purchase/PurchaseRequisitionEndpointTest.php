<?php

declare(strict_types=1);

namespace Tests\Feature\Purchase;

use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\PurchaseRequisition;
use App\Models\Purchase\PurchaseRequisitionLine;
use App\Models\Sales\Contact;
use App\Services\Purchase\PurchaseRequisitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\TestHelpers;

/**
 * Purchase requisition endpoints: the requisition in the URL is the one
 * acted on, ids a line names are the caller's organization's, and state
 * changes are checked on the locked requisition.
 */
class PurchaseRequisitionEndpointTest extends TestCase
{
    use BuildsInventory, RefreshDatabase, TestHelpers;

    private string $baseUrl = '/purchase/requisitions';
    private Contact $supplier;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['purchase.requisitions.view', 'purchase.requisitions.manage']);

        $this->supplier = Contact::factory()->supplier()->create(['organization_id' => $this->organization->id]);
        $this->product = $this->stockedProduct();
    }

    public function test_store_refuses_another_organizations_preferred_vendor(): void
    {
        $other = Organization::factory()->create();

        $this->apiPost($this->baseUrl, [
            'requisition_date' => now()->toDateString(),
            'lines' => [[
                'product_id' => $this->product->id,
                'quantity' => 2,
                'preferred_vendor_id' => Contact::factory()->supplier()->create(['organization_id' => $other->id])->id,
            ]],
        ])->assertStatus(422)->assertJsonValidationErrors('lines.0.preferred_vendor_id');

        $this->assertSame(0, PurchaseRequisition::withoutGlobalScopes()->count());
    }

    public function test_show_returns_the_requisition_with_its_preferred_vendor(): void
    {
        $requisition = $this->requisition(PurchaseRequisition::STATUS_DRAFT);

        $this->apiGet("{$this->baseUrl}/{$requisition->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $requisition->id)
            ->assertJsonPath('data.lines.0.preferred_vendor.id', $this->supplier->id)
            ->assertJsonPath('data.lines.0.preferred_vendor.name', $this->supplier->getDisplayName());
    }

    public function test_update_changes_a_draft(): void
    {
        $requisition = $this->requisition(PurchaseRequisition::STATUS_DRAFT);

        $this->apiPut("{$this->baseUrl}/{$requisition->id}", ['notes' => 'Urgent'])
            ->assertOk()
            ->assertJsonPath('data.notes', 'Urgent');
    }

    public function test_update_refuses_a_submitted_requisition(): void
    {
        $requisition = $this->requisition(PurchaseRequisition::STATUS_PENDING_APPROVAL);

        $this->apiPut("{$this->baseUrl}/{$requisition->id}", ['notes' => 'Urgent'])
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Only draft requisitions can be updated.');
    }

    public function test_destroy_deletes_a_draft_with_its_lines(): void
    {
        $requisition = $this->requisition(PurchaseRequisition::STATUS_DRAFT);

        $this->apiDelete("{$this->baseUrl}/{$requisition->id}")->assertOk();

        $this->assertSoftDeleted('purchase_requisitions', ['id' => $requisition->id]);
        $this->assertSame(0, PurchaseRequisitionLine::where('requisition_id', $requisition->id)->count());
    }

    public function test_a_stale_requisition_cancelled_meanwhile_is_not_approved(): void
    {
        $this->actingAs($this->user, 'api');
        $requisition = $this->requisition(PurchaseRequisition::STATUS_PENDING_APPROVAL);
        $stale = PurchaseRequisition::findOrFail($requisition->id);

        PurchaseRequisition::whereKey($requisition->id)->update(['status' => PurchaseRequisition::STATUS_CANCELLED]);

        $this->assertRejected(fn () => app(PurchaseRequisitionService::class)->approve($stale));
        $this->assertSame(PurchaseRequisition::STATUS_CANCELLED, $requisition->fresh()->status);
    }

    public function test_a_stale_requisition_cancelled_meanwhile_is_not_converted(): void
    {
        $this->actingAs($this->user, 'api');
        $requisition = $this->requisition(PurchaseRequisition::STATUS_APPROVED);
        $stale = PurchaseRequisition::findOrFail($requisition->id);

        PurchaseRequisition::whereKey($requisition->id)->update(['status' => PurchaseRequisition::STATUS_CANCELLED]);

        $this->assertRejected(fn () => app(PurchaseRequisitionService::class)->convertToPurchaseOrder($stale));
        $this->assertSame(0, PurchaseOrder::count());
    }

    public function test_converting_an_approved_requisition_creates_an_order_for_its_preferred_vendor(): void
    {
        $requisition = $this->requisition(PurchaseRequisition::STATUS_APPROVED);

        $this->apiPost("{$this->baseUrl}/{$requisition->id}/convert-to-po")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $order = PurchaseOrder::sole();
        $this->assertSame($this->supplier->id, (int) $order->supplier_id);
        $this->assertSame(PurchaseRequisition::STATUS_CONVERTED_TO_PO, $requisition->fresh()->status);
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

    private function requisition(string $status): PurchaseRequisition
    {
        $requisition = PurchaseRequisition::create([
            'organization_id' => $this->organization->id,
            'requisition_number' => 'PR-'.fake()->unique()->numerify('#####'),
            'requisition_date' => now()->toDateString(),
            'status' => $status,
            'requested_by' => $this->user->id,
        ]);

        $requisition->lines()->create([
            'product_id' => $this->product->id,
            'quantity' => 2,
            'preferred_vendor_id' => $this->supplier->id,
            'status' => 'open',
        ]);

        return $requisition;
    }
}
