<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Inventory\UnitOfMeasure;
use App\Models\Manufacturing\SubcontractOrder;
use App\Models\Manufacturing\SubcontractReceipt;
use App\Models\Manufacturing\SubcontractTransfer;
use App\Models\Sales\Contact;
use App\Services\Manufacturing\SubcontractingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Subcontract orders change status once, on the locked order; their transfers
 * and receipts are reached only through an order of the organization; vendors
 * are embedded by reference.
 */
class SubcontractOrderFlowTest extends TestCase
{
    use BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private Contact $vendor;
    private Product $product;
    private UnitOfMeasure $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.subcontracting.view',
            'manufacturing.subcontracting.create',
            'manufacturing.subcontracting.edit',
            'manufacturing.subcontracting.close',
            'manufacturing.subcontracting.cancel',
        ]);

        $this->vendor = Contact::factory()->create(['organization_id' => $this->organization->id]);
        $this->product = Product::factory()->create(['organization_id' => $this->organization->id]);
        $this->unit = UnitOfMeasure::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_another_organizations_transfer_and_receipt_are_not_found(): void
    {
        $theirOrder = SubcontractOrder::factory()->create([
            'organization_id' => $this->otherOrganization()->id,
            'status' => SubcontractOrder::STATUS_IN_PROCESS,
        ]);
        $warehouse = $this->foreignWarehouse();
        $transfer = SubcontractTransfer::forceCreate([
            'order_id' => $theirOrder->id,
            'transfer_date' => now()->toDateString(),
            'transfer_type' => SubcontractTransfer::TYPE_OUTWARD,
            'warehouse_id' => $warehouse->id,
            'created_by' => $theirOrder->created_by,
        ]);
        $receipt = SubcontractReceipt::forceCreate([
            'order_id' => $theirOrder->id,
            'receipt_date' => now()->toDateString(),
            'warehouse_id' => $warehouse->id,
            'status' => SubcontractReceipt::STATUS_POSTED,
            'created_by' => $theirOrder->created_by,
        ]);

        $this->apiGet("/manufacturing/subcontract-transfers/{$transfer->uuid}")->assertNotFound();
        $this->apiGet("/manufacturing/subcontract-receipts/{$receipt->uuid}")->assertNotFound();
    }

    public function test_another_organizations_vendor_is_refused(): void
    {
        $theirVendor = Contact::factory()->create(['organization_id' => $this->otherOrganization()->id]);

        $response = $this->apiPost('/manufacturing/subcontract-orders', [
            'contact_id' => $theirVendor->id,
            'lines' => [['product_id' => $this->product->id, 'ordered_quantity' => 10, 'unit_id' => $this->unit->id]],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['contact_id']);
    }

    public function test_a_stale_copy_cannot_send_a_cancelled_order(): void
    {
        $order = $this->order(SubcontractOrder::STATUS_DRAFT);
        $stale = SubcontractOrder::findOrFail($order->id);
        $service = app(SubcontractingService::class);

        $service->cancel(SubcontractOrder::findOrFail($order->id));

        try {
            $service->sendToVendor($stale);
            $this->fail('A cancelled order was sent to the vendor.');
        } catch (\InvalidArgumentException) {
            // Refused on the locked row, as expected.
        }

        $this->assertSame(SubcontractOrder::STATUS_CANCELLED, $order->fresh()->status);
    }

    public function test_orders_embed_the_vendor_by_reference(): void
    {
        $this->order(SubcontractOrder::STATUS_DRAFT);

        $vendor = $this->apiGet('/manufacturing/subcontract-orders')->assertOk()->json('data.0.vendor');

        $this->assertEqualsCanonicalizing(Contact::REFERENCE_COLUMNS, array_keys($vendor));
    }

    private function order(string $status): SubcontractOrder
    {
        return SubcontractOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_id' => $this->vendor->id,
            'created_by' => $this->user->id,
            'status' => $status,
        ]);
    }
}
